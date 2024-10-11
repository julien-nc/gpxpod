<?php
/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\GpxPod\Controller;

use DateTime;
use OCA\GpxPod\AppInfo\Application;
use OCA\GpxPod\Db\TileServerMapper;
use OCA\GpxPod\Service\MapService;
use OCA\GpxPod\Service\ProcessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;

use OCP\DB\Exception;

use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;

class ComparisonController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IInitialState $initialStateService,
		private IRootFolder $root,
		private MapService $mapService,
		private IConfig $config,
		private IAppConfig $appConfig,
		private TileServerMapper $tileServerMapper,
		private ProcessService $processService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Do the comparison, receive GET parameters.
	 * This method is called when asking comparison of two tracks from
	 * Nextcloud filesystem.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function comparePageGet(): TemplateResponse {
		$userFolder = $this->root->getUserFolder($this->userId);

		$gpxs = [];

		// gpx in GET parameters
		if (!empty($_GET)) {
			for ($i = 1; $i <= 10; $i++) {
				if (isset($_GET['path' . $i]) && $_GET['path' . $i] !== '') {
					$cleanPath = str_replace(['../', '..\\'], '', $_GET['path' . $i]);
					$file = $userFolder->get($cleanPath);
					if ($file instanceof File) {
						$content = $file->getContent();
						$gpxs[$cleanPath] = $content;
					}
				}
			}
		}

		return $this->comparePage($gpxs);
	}

	/**
	 * Compare tracks uploaded in POST data.
	 * This method is called when user provided external files
	 * in the comparison page form.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function comparePagePost(): TemplateResponse {
		$gpxs = [];

		// Get uploaded files
		// we uploaded a gpx by the POST form
		if (!empty($_POST)) {
			for ($i = 1; $i <= 10; $i++) {
				if (isset($_FILES["gpx$i"]) && $_FILES["gpx$i"]['name'] !== '') {
					$name = str_replace(' ', '_', $_FILES["gpx$i"]['name']);
					$content = file_get_contents($_FILES["gpx$i"]['tmp_name']);
					$gpxs[$name] = $content;
				}
			}
		}

		return $this->comparePage($gpxs);
	}

	/**
	 * @param array $gpxFiles
	 * @return TemplateResponse
	 * @throws Exception
	 */
	private function comparePage(array $gpxFiles): TemplateResponse {
		$process_errors = [];

		if (count($gpxFiles) > 0) {
			$names = array_keys($gpxFiles);
			$geojsons = $this->processTrackComparison($gpxFiles, $process_errors);
			$stats = $this->getStats($gpxFiles, $process_errors);
			$this->initialStateService->provideInitialState('names', $names);
			$this->initialStateService->provideInitialState('geojsons', $geojsons);
			$this->initialStateService->provideInitialState('stats', $stats);
		}

		// Settings
		$settings = [];

		$adminMaptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$maptilerApiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'maptiler_api_key', $adminMaptilerApiKey) ?: $adminMaptilerApiKey;
		$settings['maptiler_api_key'] = $maptilerApiKey;

		$userTileServers = $this->tileServerMapper->getTileServersOfUser($this->userId);
		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);
		$extraTileServers = array_merge($userTileServers, $adminTileServers);
		$settings['extra_tile_servers'] = $extraTileServers;

		$settings['show_mouse_position_control'] = $this->config->getUserValue($this->userId, Application::APP_ID, 'show_mouse_position_control');
		$settings['use_terrain'] = $this->config->getUserValue($this->userId, Application::APP_ID, 'use_terrain');
		$settings['mapStyle'] = $this->config->getUserValue($this->userId, Application::APP_ID, 'mapStyle', 'osmRaster');
		$settings['terrainExaggeration'] = $this->config->getUserValue($this->userId, Application::APP_ID, 'terrainExaggeration');
		if ($settings['terrainExaggeration'] === '') {
			$settings['terrainExaggeration'] = 2.5;
		} else {
			$settings['terrainExaggeration'] = (float)$settings['terrainExaggeration'];
		}
		$settings['compact_mode'] = '1';

		$this->initialStateService->provideInitialState('settings', $settings);

		$response = new TemplateResponse('gpxpod', 'compare');
		$csp = new ContentSecurityPolicy();
		$this->mapService->addPageCsp($csp, $extraTileServers);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param array $contents
	 * @param array $process_errors
	 * @return array
	 */
	private function processTrackComparison(array $contents, array &$process_errors): array {
		$indexes = [];
		$taggedGeo = [];

		foreach ($contents as $fname => $content) {
			$indexes[$fname] = [];
		}

		// comparison of each pair of input file
		$names = array_keys($contents);
		$i = 0;
		while ($i < count($names)) {
			$ni = $names[$i];
			$j = $i + 1;
			while ($j < count($names)) {
				$nj = $names[$j];
				try {
					$comp = $this->compareTwoGpx($contents[$ni], $ni, $contents[$nj], $nj);
					$indexes[$ni][$nj] = $comp[0];
					$indexes[$nj][$ni] = $comp[1];
				} catch (\Exception $e) {
					$process_errors[] = '[' . $ni . '|' . $nj . '] comparison error : ' . $e->getMessage();
				}
				$j++;
			}
			$i++;
		}

		// from all comparison information, convert GPX to GeoJson with lots of meta-info
		foreach ($names as $ni) {
			$taggedGeo[$ni] = [];
			foreach ($names as $nj) {
				if ($nj !== $ni) {
					if (array_key_exists($ni, $indexes) && array_key_exists($nj, $indexes[$ni])) {
						try {
							$taggedGeo[$ni][$nj] = $this->gpxTracksToGeojson($contents[$ni], $ni, $indexes[$ni][$nj]);
						} catch (\Exception $e) {
							$process_errors[] = '[' . $ni . '|' . $nj . '] geojson conversion error: ' . $e->getMessage();
						}
					}
				}
			}
		}

		return $taggedGeo;
	}

	/**
	 * @param \SimpleXMLElement $point
	 * @return bool
	 */
	private function isPointValid(\SimpleXMLElement $point): bool {
		return isset($point['lat'], $point['lon'], $point->time);
	}

	/**
	 * @param \SimpleXMLElement $points
	 * @return array
	 */
	private function getValidPoints(\SimpleXMLElement $points): array {
		$result = [];
		foreach ($points as $p) {
			if ($this->isPointValid($p)) {
				$result[] = $p;
			}
		}
		return $result;
	}

	/**
	 * build an index of divergence comparison
	 *
	 * @param string $gpxContent1
	 * @param string $id1
	 * @param string $gpxContent2
	 * @param string $id2
	 * @return array[]
	 * @throws \Exception
	 */
	private function compareTwoGpx(string $gpxContent1, string $id1, string $gpxContent2, string $id2): array {
		$gpx1 = new \SimpleXMLElement($gpxContent1);
		$gpx2 = new \SimpleXMLElement($gpxContent2);
		if (count($gpx1->trk) === 0) {
			throw new \Exception('[' . $id1 . '] At least one track per GPX is needed');
		} elseif (count($gpx2->trk) === 0) {
			throw new \Exception('[' . $id2 . '] At least one track per GPX is needed');
		} else {
			$t1 = $gpx1->trk[0];
			$t2 = $gpx2->trk[0];
			if (count($t1->trkseg) === 0) {
				throw new \Exception('[' . $id1 . '] At least one segment is needed per track');
			} elseif (count($t2->trkseg) === 0) {
				throw new \Exception('[' . $id2 . '] At least one segment is needed per track');
			} else {
				$p1 = $this->getValidPoints($t1->trkseg[0]->trkpt);
				$p2 = $this->getValidPoints($t2->trkseg[0]->trkpt);
			}
		}

		// index that will be returned
		$index1 = [];
		$index2 = [];
		// current points indexes
		$c1 = 0;
		$c2 = 0;
		# find first convergence point
		$conv = $this->findFirstConvergence($p1, $c1, $p2, $c2);

		// loop on
		while ($conv !== null) {
			// find first divergence point
			$c1 = $conv[0];
			$c2 = $conv[1];
			$div = $this->findFirstDivergence($p1, $c1, $p2, $c2);

			// if there isn't any divergence after
			if ($div === null) {
				$conv = null;
				continue;
			} else {
				// if there is a divergence
				$c1 = $div[0];
				$c2 = $div[1];
				// find first convergence point again
				$conv = $this->findFirstConvergence($p1, $c1, $p2, $c2);
				if ($conv !== null) {
					if ($div[0] - 2 > 0 && $div[1] - 2 > 0) {
						$div = [
							$div[0] - 2,
							$div[1] - 2
						];
					}
					$indexes = $this->compareBetweenDivAndConv($div, $conv, $p1, $p2, $id1, $id2);
					$index1[] = $indexes[0];
					$index2[] = $indexes[1];
				}
			}
		}
		return [$index1, $index2];
	}

	/**
	 * returns indexes of the first convergence point found
	 * from c1 and c2 in the point tables
	 *
	 * @param array $p1
	 * @param int $c1
	 * @param array $p2
	 * @param int $c2
	 * @return int[]|null
	 */
	private function findFirstConvergence(array $p1, int $c1, array $p2, int $c2): ?array {
		$ct1 = $c1;
		$p1Length = count($p1);
		$p2Length = count($p2);
		while ($ct1 < $p1Length) {
			$ct2 = $c2;
			while ($ct2 < $p2Length && $this->processService->distanceBetweenGpxPoints($p1[$ct1], $p2[$ct2]) > 70) {
				$ct2 += 1;
			}
			if ($ct2 < $p2Length) {
				// we found a convergence point
				return [$ct1, $ct2];
			}
			$ct1 += 1;
		}
		return null;
	}

	/**
	 * find the first divergence by using findFirstConvergence
	 *
	 * @param array $p1
	 * @param int $c1
	 * @param array $p2
	 * @param int $c2
	 * @return array|null
	 */
	private function findFirstDivergence(array $p1, int $c1, array $p2, int $c2): ?array {
		// we are in a convergence state so we need to advance
		$ct1 = $c1 + 1;
		$ct2 = $c2 + 1;
		$conv = $this->findFirstConvergence($p1, $ct1, $p2, $ct2);
		while ($conv !== null) {
			// if it's still convergent, go on
			if ($conv[0] === $ct1 && $conv[1] === $ct2) {
				$ct1 += 1;
				$ct2 += 1;
			} elseif ($conv[0] === $ct1) {
				// if the convergence made only ct2 advance
				$ct1 += 1;
				$ct2 = $conv[1] + 1;
			} elseif ($conv[1] === $ct2) {
				// if the convergence made only ct1 advance
				$ct2 += 1;
				$ct1 = $conv[0] + 1;
			} else {
				// the two tracks advanced to find next convergence, it's a divergence !
				return [
					$ct1 + 1,
					$ct2 + 1,
				];
			}

			$conv = $this->findFirstConvergence($p1, $ct1, $p2, $ct2);
		}

		return null;
	}

	/**
	 * determine who's best in time and distance during this divergence
	 *
	 * @param array $div
	 * @param array $conv
	 * @param array $p1
	 * @param array $p2
	 * @param string $id1
	 * @param string $id2
	 * @return array[]
	 * @throws \Exception
	 */
	private function compareBetweenDivAndConv(array $div, array $conv, array $p1, array $p2, string $id1, string $id2): array {
		$result1 = [
			'divPoint' => $div[0],
			'convPoint' => $conv[0],
			'comparedTo' => $id2,
		];
		$result2 = [
			'divPoint' => $div[1],
			'convPoint' => $conv[1],
			'comparedTo' => $id1,
		];
		// positive deniv
		$posden1 = 0;
		$posden2 = 0;
		$lastp = null;
		$upBegin = null;
		$isGoingUp = false;
		$lastDeniv = null;
		//for p in p1[div[0]:conv[0]+1]:
		$slice = [];
		$ind = 0;
		foreach ($p1 as $p) {
			if ($ind >= $div[0] && $ind <= $conv[0]) {
				$slice[] = $p;
			}
			$ind++;
		}
		//$slice = array_slice($p1, $div[0], ($conv[0] - $div[0]) + 1);
		foreach ($slice as $p) {
			$ele = empty($p->ele) ? 0 : (float)$p->ele;
			if ($lastp !== null) {
				$lastpEle = empty($lastp->ele) ? 0 : (float)$lastp->ele;
				$deniv = $ele - $lastpEle;
			}
			if ($lastDeniv !== null) {
				// we start to go up
				if (!$isGoingUp && $deniv > 0) {
					$upBegin = $lastpEle;
					$isGoingUp = true;
				} elseif ($isGoingUp && $deniv < 0) {
					// we add the up portion
					$posden1 += $lastpEle - $upBegin;
					$isGoingUp = false;
				}
			}
			// update variables
			if ($lastp !== null) {
				$lastDeniv = $deniv;
			}
			$lastp = $p;
		}

		$lastp = null;
		$upBegin = null;
		$isGoingUp = false;
		$lastDeniv = null;
		//for p in p2[div[1]:conv[1]+1]:
		$slice = [];
		$ind = 0;
		foreach ($p2 as $p) {
			if ($ind >= $div[1] && $ind <= $conv[1]) {
				$slice[] = $p;
			}
			$ind++;
		}
		//$slice2 = array_slice($p2, $div[1], ($conv[1] - $div[1]) + 1);
		foreach ($slice as $p) {
			$ele = empty($p->ele) ? 0 : (float)$p->ele;
			if ($lastp !== null) {
				$lastpEle = empty($lastp->ele) ? 0 : (float)$lastp->ele;
				$deniv = $ele - $lastpEle;
			}
			if ($lastDeniv !== null) {
				// we start a way up
				if (!$isGoingUp && $deniv > 0) {
					$upBegin = $lastpEle;
					$isGoingUp = true;
				} elseif ($isGoingUp && $deniv < 0) {
					// we add the up portion
					$posden2 += $lastpEle - $upBegin;
					$isGoingUp = false;
				}
			}
			// update variables
			if ($lastp !== null) {
				$lastDeniv = $deniv;
			}
			$lastp = $p;
		}

		$result1['isPositiveDenivBetter'] = ($posden1 < $posden2);
		$result1['positiveDeniv'] = $posden1;
		$result1['positiveDeniv_other'] = $posden2;
		$result2['isPositiveDenivBetter'] = ($posden2 <= $posden1);
		$result2['positiveDeniv'] = $posden2;
		$result2['positiveDeniv_other'] = $posden1;

		// distance
		$dist1 = 0;
		$dist2 = 0;
		$lastp = null;
		//for p in p1[div[0]:conv[0]+1]:
		$slice = [];
		$ind = 0;
		foreach ($p1 as $p) {
			if ($ind >= $div[0] && $ind <= $conv[0]) {
				$slice[] = $p;
			}
			$ind++;
		}
		//$slice = array_slice($p1, $div[0], ($conv[0] - $div[0]) + 1);
		foreach ($slice as $p) {
			if ($lastp !== null) {
				$dist1 += $this->processService->distanceBetweenGpxPoints($lastp, $p);
			}
			$lastp = $p;
		}
		$lastp = null;
		//for p in p2[div[1]:conv[1]+1]:
		$slice = [];
		$ind = 0;
		foreach ($p2 as $p) {
			if ($ind >= $div[1] && $ind <= $conv[1]) {
				$slice[] = $p;
			}
			$ind++;
		}
		//$slice2 = array_slice($p2, $div[1], ($conv[1] - $div[1]) + 1);
		foreach ($slice as $p) {
			if ($lastp !== null) {
				$dist2 += $this->processService->distanceBetweenGpxPoints($lastp, $p);
			}
			$lastp = $p;
		}

		$result1['isDistanceBetter'] = ($dist1 < $dist2);
		$result1['distance'] = $dist1;
		$result1['distance_other'] = $dist2;
		$result2['isDistanceBetter'] = ($dist1 >= $dist2);
		$result2['distance'] = $dist2;
		$result2['distance_other'] = $dist1;

		// time
		if (empty($p1[$div[0]]->time) || empty($p1[$conv[0]]->time)) {
			throw new \Exception('Time data is needed for comparison in ' . $id1);
		}
		$tdiv1 = new DateTime($p1[$div[0]]->time);
		$tconv1 = new DateTime($p1[$conv[0]]->time);
		$t1 = $tconv1->getTimestamp() - $tdiv1->getTimestamp();

		if (empty($p2[$div[1]]->time) || empty($p2[$conv[1]]->time)) {
			throw new \Exception('Time data is needed for comparison in ' . $id2);
		}
		$tdiv2 = new DateTime($p2[$div[1]]->time);
		$tconv2 = new DateTime($p2[$conv[1]]->time);
		$t2 = $tconv2->getTimestamp() - $tdiv2->getTimestamp();

		$result1['isTimeBetter'] = ($t1 < $t2);
		$result1['time'] = $t1;
		$result1['time_other'] = $t2;
		$result2['isTimeBetter'] = ($t1 >= $t2);
		$result2['time'] = $t2;
		$result2['time_other'] = $t1;

		return [$result1, $result2];
	}

	/**
	 * converts the gpx string input to geojson
	 *
	 * @param string $gpx_content
	 * @param string $name
	 * @param array $divList
	 * @return array|null
	 * @throws \Exception
	 */
	private function gpxTracksToGeojson(string $gpx_content, string $name, array $divList): ?array {
		$currentlyInDivergence = false;
		$currentSectionPointList = [];
		$currentProperties = [];

		$sections = [];
		$properties = [];

		$gpx = new \SimpleXMLElement($gpx_content);
		foreach ($gpx->trk as $track) {
			$featureList = [];
			$lastPoint = null;
			$pointIndex = 0;
			foreach ($track->trkseg as $segment) {
				foreach ($segment->trkpt as $point) {
					if (!$this->isPointValid($point)) {
						continue;
					}
					#print 'Point at ({0},{1}) -> {2}'.format(point.latitude, point.longitude, point.elevation)
					if ($lastPoint !== null) {
						// is the point in a divergence ?
						$isDiv = false;
						foreach ($divList as $d) {
							if ($pointIndex > $d['divPoint'] && $pointIndex <= $d['convPoint']) {
								// we are in a divergence
								$isDiv = true;
								// is it the first point in div ?
								if (!$currentlyInDivergence) {
									// it is the first div point, we add previous section
									$currentSectionPointList[] = $lastPoint;
									$sections[] = $currentSectionPointList;
									// we update properties with lastPoint infos (the last in previous section)
									$currentProperties['id'] .= $pointIndex - 1;
									$currentProperties['elevationEnd'] = (float)$lastPoint->ele;
									$currentProperties['timestampEnd'] = (new DateTime((string)$lastPoint->time))->getTimestamp();
									// we add previous properties and reset tmp vars
									$properties[] = $currentProperties;
									$currentSectionPointList = [];
									// we add the last point that is the junction
									// between the two sections
									$currentSectionPointList[] = $lastPoint;

									$currentProperties = [
										'id' => ($pointIndex - 1) . '-',
										'me' => $name,
										'comparedTo' => $d['comparedTo'],
										'elevationBegin' => (float)$lastPoint->ele,
										'timestampBegin' => (new DateTime((string)$lastPoint->time))->getTimestamp(),
										'distance' => $d['distance'],
										'distanceOther' => $d['distance_other'],
										'time' => $d['time'],
										'timeOther' => $d['time_other'],
										'positiveDeniv' => $d['positiveDeniv'],
										'positiveDenivOther' => $d['positiveDeniv_other'],
									];
									$currentlyInDivergence = true;

									if ($d['isDistanceBetter']) {
										$currentProperties['shorter'] = true;
									} else {
										$currentProperties['longer'] = true;
									}
									if ($d['isTimeBetter']) {
										$currentProperties['quicker'] = true;
									} else {
										$currentProperties['slower'] = true;
									}
									if ($d['isPositiveDenivBetter']) {
										$currentProperties['lessPositiveDeniv'] = true;
									} else {
										$currentProperties['morePositiveDeniv'] = true;
									}
								}
							}
						}

						// if we were in a divergence and now are NOT in a divergence
						if ($currentlyInDivergence && !$isDiv) {
							// it is the first NON div point, we add previous section
							$currentSectionPointList[] = $lastPoint;
							$currentSectionPointList[] = $point;
							$sections[] = $currentSectionPointList;
							// we update properties with lastPoint infos (the last in previous section)
							$currentProperties['id'] .= $pointIndex;
							$currentProperties['elevationEnd'] = (float)$point->ele;
							$currentProperties['timestampEnd'] = (new DateTime((string)$point->time))->getTimestamp();
							// we add previous properties and reset tmp vars
							$properties[] = $currentProperties;
							$currentSectionPointList = [];

							$currentProperties = [
								'id' => $pointIndex . '-',
								'elevationBegin' => (float)$point->ele,
								'timestampBegin' => (new DateTime((string)$point->time))->getTimestamp(),
							];
							$currentlyInDivergence = false;
						}

						$currentSectionPointList[] = $point;
					} else {
						// this is the first point
						$currentProperties['id'] = 'begin-';
						$currentProperties['timestampBegin'] = (new DateTime((string)$point->time))->getTimestamp();
						$currentProperties['elevationBegin'] = (float)$point->ele;
					}

					$lastPoint = $point;
					$pointIndex += 1;
				}
			}

			if (count($currentSectionPointList) > 0) {
				$sections[] = $currentSectionPointList;
				$currentProperties['id'] .= 'end';
				$currentProperties['timestampEnd'] = (new DateTime((string)$lastPoint->time))->getTimestamp();
				$currentProperties['elevationEnd'] = (float)$lastPoint->ele;
				$properties[] = $currentProperties;
			}

			// for each section, we add a Feature
			foreach (range(0, count($sections) - 1) as $i) {
				$coords = [];
				foreach ($sections[$i] as $p) {
					$coords[] = [
						(float)$p['lon'],
						(float)$p['lat']
					];
				}
				$featureList[] = [
					'type' => 'Feature',
					'id' => (string)$i,
					'properties' => $properties[$i],
					'geometry' => [
						'coordinates' => $coords,
						'type' => 'LineString'
					],
				];
			}

			//fc = geojson.FeatureCollection(featureList, id=name)
			$fc = [
				'type' => 'FeatureCollection',
				'features' => $featureList,
				'id' => $name,
			];
			return $fc;
		}
		return null;
	}

	/**
	 * return global stats for each track
	 *
	 * @param array $contents
	 * @param array $process_errors
	 * @return array
	 */
	private function getStats(array $contents, array &$process_errors): array {
		$STOPPED_SPEED_THRESHOLD = 0.9;
		$stats = [];

		foreach ($contents as $name => $gpx_content) {
			try {
				$gpx = new \SimpleXMLElement($gpx_content);

				$nbpoints = 0;
				$total_distance = 0;
				$total_duration = 0;
				$date_begin = null;
				$date_end = null;
				$pos_elevation = 0;
				$neg_elevation = 0;
				$max_speed = 0;
				$avg_speed = 'null';
				$moving_time = 0;
				$moving_distance = 0;
				$stopped_distance = 0;
				$moving_max_speed = 0;
				$moving_avg_speed = 0;
				$stopped_time = 0;

				$isGoingUp = false;
				$lastDeniv = null;
				$upBegin = null;
				$downBegin = null;
				$lastTime = null;

				// TRACKS
				foreach ($gpx->trk as $track) {
					foreach ($track->trkseg as $segment) {
						$lastPoint = null;
						$lastTime = null;
						$pointIndex = 0;
						$lastDeniv = null;
						foreach ($segment->trkpt as $point) {
							if (!$this->isPointValid($point)) {
								continue;
							}
							$nbpoints++;
							if (empty($point->ele)) {
								$pointele = null;
							} else {
								$pointele = (float)$point->ele;
							}
							if (empty($point->time)) {
								$pointtime = null;
							} else {
								$pointtime = new \DateTime((string)$point->time);
							}
							if ($lastPoint !== null && (!empty($lastPoint->ele))) {
								$lastPointele = (float)$lastPoint->ele;
							} else {
								$lastPointele = null;
							}
							if ($lastPoint !== null && (!empty($lastPoint->time))) {
								$lastTime = new \DateTime((string)$lastPoint->time);
							} else {
								$lastTime = null;
							}
							if ($lastPoint !== null) {
								$distToLast = $this->processService->distanceBetweenGpxPoints($lastPoint, $point);
							} else {
								$distToLast = null;
							}
							if ($pointIndex === 0) {
								if ($pointtime !== null && ($date_begin === null || $pointtime < $date_begin)) {
									$date_begin = $pointtime;
								}
								$downBegin = $pointele;
							}

							if ($lastPoint !== null && $pointtime !== null && $lastTime !== null) {
								$t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

								$speed = 0;
								if ($t > 0) {
									$speed = $distToLast / $t;
									$speed = $speed / 1000;
									$speed = $speed * 3600;
									if ($speed > $max_speed) {
										$max_speed = $speed;
									}
								}

								if ($speed <= $STOPPED_SPEED_THRESHOLD) {
									$stopped_time += $t;
									$stopped_distance += $distToLast;
								} else {
									$moving_time += $t;
									$moving_distance += $distToLast;
								}
							}
							if ($lastPoint !== null) {
								$total_distance += $distToLast;
							}
							if ($lastPoint !== null && $pointele !== null && (!empty($lastPoint->ele))) {
								$deniv = $pointele - (float)$lastPoint->ele;
							}
							if ($lastDeniv !== null && $pointele !== null && $lastPoint !== null && (!empty($lastPoint->ele))) {
								// we start to go up
								if ($isGoingUp === false && $deniv > 0) {
									$upBegin = (float)$lastPoint->ele;
									$isGoingUp = true;
									$neg_elevation += ($downBegin - (float)$lastPoint->ele);
								}
								if ($isGoingUp === true && $deniv < 0) {
									// we add the up portion
									$pos_elevation += ((float)$lastPointele - $upBegin);
									$isGoingUp = false;
									$downBegin = (float)$lastPoint->ele;
								}
							}
							// update vars
							if ($lastPoint !== null && $pointele !== null && (!empty($lastPoint->ele))) {
								$lastDeniv = $deniv;
							}

							$lastPoint = $point;
							$pointIndex += 1;
						}
					}

					if ($lastTime !== null && ($date_end === null || $lastTime > $date_end)) {
						$date_end = $lastTime;
					}
				}

				# ROUTES
				foreach ($gpx->rte as $route) {
					$lastPoint = null;
					$lastTime = null;
					$pointIndex = 0;
					$lastDeniv = null;
					foreach ($route->rtept as $point) {
						$nbpoints++;
						if (empty($point->ele)) {
							$pointele = null;
						} else {
							$pointele = (float)$point->ele;
						}
						if (empty($point->time)) {
							$pointtime = null;
						} else {
							$pointtime = new \DateTime($point->time);
						}
						if ($lastPoint !== null && (!empty($lastPoint->ele))) {
							$lastPointele = (float)$lastPoint->ele;
						} else {
							$lastPointele = null;
						}
						if ($lastPoint !== null && (!empty($lastPoint->time))) {
							$lastTime = new \DateTime((string)$lastPoint->time);
						} else {
							$lastTime = null;
						}
						if ($lastPoint !== null) {
							$distToLast = $this->processService->distanceBetweenGpxPoints($lastPoint, $point);
						} else {
							$distToLast = null;
						}
						if ($pointIndex === 0) {
							if ($pointtime !== null && ($date_begin === null || $pointtime < $date_begin)) {
								$date_begin = $pointtime;
							}
							$downBegin = $pointele;
						}

						if ($lastPoint !== null && $pointtime !== null && $lastTime !== null) {
							$t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

							$speed = 0;
							if ($t > 0) {
								$speed = $distToLast / $t;
								$speed = $speed / 1000;
								$speed = $speed * 3600;
								if ($speed > $max_speed) {
									$max_speed = $speed;
								}
							}

							if ($speed <= $STOPPED_SPEED_THRESHOLD) {
								$stopped_time += $t;
								$stopped_distance += $distToLast;
							} else {
								$moving_time += $t;
								$moving_distance += $distToLast;
							}
						}
						if ($lastPoint !== null) {
							$total_distance += $distToLast;
						}
						if ($lastPoint !== null && $pointele !== null && (!empty($lastPoint->ele))) {
							$deniv = $pointele - (float)$lastPoint->ele;
						}
						if ($lastDeniv !== null && $pointele !== null && $lastPoint !== null && (!empty($lastPoint->ele))) {
							// we start to go up
							if ($isGoingUp === false && $deniv > 0) {
								$upBegin = (float)$lastPoint->ele;
								$isGoingUp = true;
								$neg_elevation += ($downBegin - (float)$lastPoint->ele);
							}
							if ($isGoingUp === true && $deniv < 0) {
								// we add the up portion
								$pos_elevation += ((float)$lastPointele - $upBegin);
								$isGoingUp = false;
								$downBegin = (float)$lastPoint->ele;
							}
						}
						// update vars
						if ($lastPoint !== null && $pointele !== null && (!empty($lastPoint->ele))) {
							$lastDeniv = $deniv;
						}

						$lastPoint = $point;
						$pointIndex += 1;
					}

					if ($lastTime !== null && ($date_end === null || $lastTime > $date_end)) {
						$date_end = $lastTime;
					}
				}

				# TOTAL STATS : duration, avg speed, avg_moving_speed
				if ($date_end !== null && $date_begin !== null) {
					$total_duration = abs($date_end->getTimestamp() - $date_begin->getTimestamp());
					if ($total_duration === 0) {
						$avg_speed = 0;
					} else {
						$avg_speed = $total_distance / $total_duration;
						$avg_speed = $avg_speed / 1000;
						$avg_speed = $avg_speed * 3600;
					}
				}

				// determination of real moving average speed from moving time
				if ($moving_time > 0) {
					$moving_avg_speed = $total_distance / $moving_time;
					$moving_avg_speed = $moving_avg_speed / 1000;
					$moving_avg_speed = $moving_avg_speed * 3600;
				}

				if ($date_begin !== null) {
					$date_begin = $date_begin->getTimestamp();
				}
				if ($date_end !== null) {
					$date_end = $date_end->getTimestamp();
				}

				$stats[$name] = [
					'length_2d' => $total_distance,
					//					'length_3d' => $total_distance,
					'total_duration' => $total_duration,
					'moving_time' => $moving_time,
					'stopped_time' => $stopped_time,
					'max_speed' => $max_speed,
					'moving_avg_speed' => $moving_avg_speed,
					'avg_speed' => $avg_speed,
					'total_uphill' => $pos_elevation,
					'total_downhill' => $neg_elevation,
					'started' => $date_begin,
					'ended' => $date_end,
					'nbpoints' => $nbpoints,
				];
			} catch (\Exception $e) {
				$process_errors[] = '[' . $name . '] stats compute error : ' . $e->getMessage();
			}
		}

		return $stats;
	}
}
