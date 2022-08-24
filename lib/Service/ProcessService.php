<?php

/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\GpxPod\Service;

use DateTime;
use OC\Files\Node\File;
use OC\User\NoUserException;
use OCA\GpxPod\Db\DirectoryMapper;
use OCA\GpxPod\Db\Track;
use OCA\GpxPod\Db\TrackMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDBConnection;

use OCA\GpxPod\AppInfo\Application;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;

//require_once('utils.php');

class ProcessService {

	/**
	 * @var IDBConnection
	 */
	private $dbconnection;
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var ConversionService
	 */
	private $conversionService;
	/**
	 * @var ToolsService
	 */
	private $toolsService;
	/**
	 * @var DirectoryMapper
	 */
	private $directoryMapper;
	/**
	 * @var TrackMapper
	 */
	private $trackMapper;

	public function __construct(IDBConnection $dbconnection,
								LoggerInterface $logger,
								IConfig $config,
								ConversionService $conversionService,
								ToolsService $toolsService,
								DirectoryMapper $directoryMapper,
								TrackMapper $trackMapper,
								IRootFolder $root) {
		$this->dbconnection = $dbconnection;
		$this->root = $root;
		$this->logger = $logger;
		$this->config = $config;
		$this->conversionService = $conversionService;
		$this->toolsService = $toolsService;
		$this->directoryMapper = $directoryMapper;
		$this->trackMapper = $trackMapper;
	}

	/**
	 * recursively search files with given extensions (case insensitive)
	 */
	public function searchFilesWithExt(Node $folder, bool $sharedAllowed, bool $mountedAllowed, array $extensions): array {
		$res = [];
		foreach ($folder->getDirectoryListing() as $node) {
			// top level files with matching ext
			if ($node->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
				$fext = '.'.strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
				if (in_array($fext, $extensions)) {
					if ($sharedAllowed || !$node->isShared()) {
						$res[] = $node;
					}
				}
			} else {
				// top level folders
				if (    ($mountedAllowed || !$node->isMounted())
					&& ($sharedAllowed || !$node->isShared())
				) {
					$subres = $this->searchFilesWithExt($node, $sharedAllowed, $mountedAllowed, $extensions);
					$res = array_merge($res, $subres);
				}
			}
		}
		return $res;
	}

	/*
	 * get marker string for each gpx file
	 * return an array indexed by trackname
	 */
	private function getMarkersFromFiles($gpxs_to_process, $userId) {
		$result = [];
		foreach ($gpxs_to_process as $gpxfile) {
			$markerJson = $this->getMarkerFromFile($gpxfile, $userId);
			if ($markerJson !== null) {
				$result[$gpxfile->getPath()] = $markerJson;
			}
		}
		return $result;
	}

	/**
	 * @param string $userId
	 * @param int $directoryId
	 * @param bool $sharedAllowed
	 * @param bool $mountedAllowed
	 * @param bool $processAll
	 * @return void
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OC\User\NoUserException
	 */
	public function processGpxFiles(string $userId, int $directoryId,
									bool $sharedAllowed, bool $mountedAllowed, bool $processAll): void
	{
		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUser($directoryId, $userId);
		} catch (\OCP\DB\Exception $e) {
			return;
		}

		/** @var Track[] $dbDirectoryTracks */
		$dbDirectoryTracks = $this->trackMapper->getDirectoryTracksOfUser($userId, $directoryId);
		$dbTrackByPath = [];
		foreach ($dbDirectoryTracks as $track) {
			$dbTrackByPath[$track->getTrackpath()] = $track;
		}

		$userFolder = $this->root->getUserFolder($userId);
		$userfolder_path = $userFolder->getPath();

		// find gpx files in the directory (in the file system)
		$gpxFiles = array_filter($userFolder->get($dbDir->getPath())->getDirectoryListing(), static function(Node $node) use ($sharedAllowed) {
			if ($node instanceof File) {
				$fileExtension = '.' . strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
				if ($fileExtension === '.gpx') {
					if ($sharedAllowed || !$node->isShared()) {
						return true;
					}
				}
			}
			return false;
		});

		// CHECK what is to be processed
		// TODO switch to filter, find a way to get rid of the CRC array
		// $filesToProcess = array_filter()

		$gpxFilesToProcess = [];
		$newCRC = [];
		foreach ($gpxFiles as $gg) {
			$gpx_relative_path = str_replace($userfolder_path, '', $gg->getPath());
			$gpx_relative_path = rtrim($gpx_relative_path, '/');
			$gpx_relative_path = str_replace('//', '/', $gpx_relative_path);
			// TODO try to switch to the etag
			$newCRC[$gpx_relative_path] = $gg->getMTime() . '.' . $gg->getSize();
			// if the file is not in the DB or if its content hash has changed
			if (   (!isset($dbTrackByPath[$gpx_relative_path]))
				|| $dbTrackByPath[$gpx_relative_path]->getContenthash() !== $newCRC[$gpx_relative_path]
				|| $processAll
			) {
				// not in DB or hash changed
				$gpxFilesToProcess[] = $gg;
			}
		}

		$markers = $this->getMarkersFromFiles($gpxFilesToProcess, $userId);

		foreach ($markers as $trackpath => $marker) {
			$gpx_relative_path = str_replace($userfolder_path, '', $trackpath);
			$gpx_relative_path = rtrim($gpx_relative_path, '/');
			$gpx_relative_path = str_replace('//', '/', $gpx_relative_path);

			if (!isset($dbTrackByPath[$gpx_relative_path])) {
				$this->trackMapper->createTrack(
					$gpx_relative_path, $userId, $directoryId,
					$newCRC[$gpx_relative_path], $marker
				);
			} else {
				$trackId = $dbTrackByPath[$gpx_relative_path]->getId();
				$this->trackMapper->updateTrack(
					$trackId, $userId,
					$newCRC[$gpx_relative_path], $marker
				);
			}
		}
	}


	/** return marker string that will be used in the web interface
	 *   each marker is : [x,y,filename,distance,duration,datebegin,dateend,poselevation,negelevation]
	 */
	private function getMarkerFromFile($file, string $userId) {
		$DISTANCE_BETWEEN_SHORT_POINTS = 300;
		$STOPPED_SPEED_THRESHOLD = 0.9;

		$name = $file->getName();

		// get path relative to user '/'
		$userFolder = $this->root->getUserFolder($userId);
		$userfolder_path = $userFolder->getPath();
		$dirname = dirname($file->getPath());
		$gpx_relative_dir = str_replace($userfolder_path, '', $dirname);
		if ($gpx_relative_dir !== '') {
			$gpx_relative_dir = rtrim($gpx_relative_dir, '/');
			$gpx_relative_dir = str_replace('//', '/', $gpx_relative_dir);
		} else {
			$gpx_relative_dir = '/';
		}

		$gpx_content = $file->getContent();

		$lat = '0';
		$lon = '0';
		$total_distance = 0;
		$total_duration = 0;
		$date_begin = null;
		$date_end = null;

		$distAccCumulEle = 0;
		$pos_elevation = 0;
		$neg_elevation = 0;
		$min_elevation = null;
		$max_elevation = null;

		$avg_speed = 'null';
		$moving_time = 0;
		$moving_distance = 0;
		$stopped_distance = 0;
		$moving_max_speed = 0;
		$moving_avg_speed = 0;
		$stopped_time = 0;
		$north = null;
		$south = null;
		$east = null;
		$west = null;
		$shortPointList = [];
		$lastShortPoint = null;
		$trackNameList = '[';
		$linkurl = '';
		$linktext = '';

		$pointsBySegment = [];
		$lastTime = null;

		try{
			$gpx = new SimpleXMLElement($gpx_content);
		}
		catch (\Exception $e) {
			$this->logger->error(
				'Exception in ' . $name . ' gpx parsing : ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
			return null;
		}

		if (count($gpx->trk) === 0 && count($gpx->rte) === 0 && count($gpx->wpt) === 0) {
			$this->logger->error(
				'Nothing to parse in ' . $name . ' gpx file',
				['app' => Application::APP_ID]
			);
			return null;
		}

		// METADATA
		if (!empty($gpx->metadata) && !empty($gpx->metadata->link)) {
			$linkurl = $gpx->metadata->link['href'];
			if (!empty($gpx->metadata->link->text)) {
				$linktext = $gpx->metadata->link->text;
			}
		}

		// TRACKS
		foreach ($gpx->trk as $track) {
			$trackname = str_replace("\n", '', $track->name);
			if (empty($trackname)) {
				$trackname = '';
			}
			$trackname = str_replace('"', "'", $trackname);
			$trackNameList .= sprintf('"%s",', $trackname);
			foreach ($track->trkseg as $segment) {
				$lastPoint = null;
				$lastTime = null;
				$pointIndex = 0;
				$pointsBySegment[] = $segment->trkpt;
				foreach ($segment->trkpt as $point) {
					if (empty($point['lat']) || empty($point['lon'])) {
						continue;
					}
					if (empty($point->ele)) {
						$pointele = null;
					} else {
						$pointele = floatval($point->ele);
					}
					if (empty($point->time)) {
						$pointtime = null;
					} else {
						$pointtime = new DateTime($point->time);
					}
					if ($lastPoint !== null && (!empty($lastPoint->ele))) {
						$lastPointele = floatval($lastPoint->ele);
					} else {
						$lastPointele = null;
					}
					if ($lastPoint !== null && (!empty($lastPoint->time))) {
						$lastTime = new DateTime($lastPoint->time);
					} else {
						$lastTime = null;
					}
					if ($lastPoint !== null) {
						$distToLast = $this->distance($lastPoint, $point);
					} else {
						$distToLast = null;
					}
					$pointlat = floatval($point['lat']);
					$pointlon = floatval($point['lon']);
					if ($pointIndex === 0) {
						if ($lat === '0' && $lon === '0') {
							$lat = $pointlat;
							$lon = $pointlon;
						}
						if ($pointtime !== null && ($date_begin === null || $pointtime < $date_begin)) {
							$date_begin = $pointtime;
						}
						if ($north === null) {
							$north = $pointlat;
							$south = $pointlat;
							$east = $pointlon;
							$west = $pointlon;
						}
						$shortPointList[] = [$pointlat, $pointlon];
						$lastShortPoint = $point;
					}

					if ($lastShortPoint !== null) {
						// if the point is more than 500m far from the last in shortPointList
						// we add it
						if ($this->distance($lastShortPoint, $point) > $DISTANCE_BETWEEN_SHORT_POINTS) {
							$shortPointList[] = [$pointlat, $pointlon];
							$lastShortPoint = $point;
						}
					}
					if ($pointlat > $north) {
						$north = $pointlat;
					}
					if ($pointlat < $south) {
						$south = $pointlat;
					}
					if ($pointlon > $east) {
						$east = $pointlon;
					}
					if ($pointlon < $west) {
						$west = $pointlon;
					}
					if ($pointele !== null && ($min_elevation === null || $pointele < $min_elevation)) {
						$min_elevation = $pointele;
					}
					if ($pointele !== null && ($max_elevation === null || $pointele > $max_elevation)) {
						$max_elevation = $pointele;
					}
					if ($lastPoint !== null && $pointtime !== null && $lastTime !== null) {
						$t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

						$speed = 0;
						if ($t > 0) {
							$speed = $distToLast / $t;
							$speed = $speed / 1000;
							$speed = $speed * 3600;
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

					$lastPoint = $point;
					$pointIndex += 1;
				}

				if ($lastTime !== null && ($date_end === null || $lastTime > $date_end)) {
					$date_end = $lastTime;
				}
			}

		}

		# ROUTES
		foreach ($gpx->rte as $route) {
			$routename = str_replace("\n", '', $route->name);
			if (empty($routename)) {
				$routename = '';
			}
			$routename = str_replace('"', "'", $routename);
			$trackNameList .= sprintf('"%s",', $routename);

			$lastPoint = null;
			$lastTime = null;
			$pointIndex = 0;
			$pointsBySegment[] = $route->rtept;
			foreach ($route->rtept as $point) {
				if (empty($point['lat']) || empty($point['lon'])) {
					continue;
				}
				if (empty($point->ele)) {
					$pointele = null;
				} else {
					$pointele = floatval($point->ele);
				}
				if (empty($point->time)) {
					$pointtime = null;
				} else {
					$pointtime = new DateTime($point->time);
				}
				if ($lastPoint !== null && (!empty($lastPoint->ele))) {
					$lastPointele = floatval($lastPoint->ele);
				} else {
					$lastPointele = null;
				}
				if ($lastPoint !== null && (!empty($lastPoint->time))) {
					$lastTime = new DateTime($lastPoint->time);
				} else {
					$lastTime = null;
				}
				if ($lastPoint !== null) {
					$distToLast = $this->distance($lastPoint, $point);
				} else {
					$distToLast = null;
				}
				$pointlat = floatval($point['lat']);
				$pointlon = floatval($point['lon']);
				if ($pointIndex === 0) {
					if ($lat === '0' && $lon === '0') {
						$lat = $pointlat;
						$lon = $pointlon;
					}
					if ($pointtime !== null && ($date_begin === null || $pointtime < $date_begin)) {
						$date_begin = $pointtime;
					}
					if ($north === null) {
						$north = $pointlat;
						$south = $pointlat;
						$east = $pointlon;
						$west = $pointlon;
					}
					$shortPointList[] = [$pointlat, $pointlon];
					$lastShortPoint = $point;
				}

				if ($lastShortPoint !== null) {
					// if the point is more than 500m far from the last in shortPointList
					// we add it
					if ($this->distance($lastShortPoint, $point) > $DISTANCE_BETWEEN_SHORT_POINTS) {
						$shortPointList[] = [$pointlat, $pointlon];
						$lastShortPoint = $point;
					}
				}
				if ($pointlat > $north) {
					$north = $pointlat;
				}
				if ($pointlat < $south) {
					$south = $pointlat;
				}
				if ($pointlon > $east) {
					$east = $pointlon;
				}
				if ($pointlon < $west) {
					$west = $pointlon;
				}
				if ($pointele !== null && ($min_elevation === null || $pointele < $min_elevation)) {
					$min_elevation = $pointele;
				}
				if ($pointele !== null && ($max_elevation === null || $pointele > $max_elevation)) {
					$max_elevation = $pointele;
				}
				if ($lastPoint !== null && $pointtime !== null && $lastTime !== null) {
					$t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

					$speed = 0;
					if ($t > 0) {
						$speed = $distToLast / $t;
						$speed = $speed / 1000;
						$speed = $speed * 3600;
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
				$avg_speed = sprintf('%.2f', $avg_speed);
			}
		} else {
			$total_duration = 0;
		}

		// determination of real moving average speed from moving time
		$moving_avg_speed = 0;
		$moving_pace = 0;
		if ($moving_time > 0) {
			$moving_avg_speed = $total_distance / $moving_time;
			$moving_avg_speed = $moving_avg_speed / 1000;
			$moving_avg_speed = $moving_avg_speed * 3600;
			$moving_avg_speed = sprintf('%.2f', $moving_avg_speed);
			// pace in minutes/km
			$moving_pace = $moving_time / $total_distance;
			$moving_pace = $moving_pace / 60;
			$moving_pace = $moving_pace * 1000;
			$moving_pace = sprintf('%.2f', $moving_pace);
		}

		# WAYPOINTS
		foreach ($gpx->wpt as $waypoint) {
			$shortPointList[] = [
				$waypoint['lat'],
				$waypoint['lon']
			];

			$waypointlat = floatval($waypoint['lat']);
			$waypointlon = floatval($waypoint['lon']);

			if ($lat === '0' && $lon === '0') {
				$lat = $waypointlat;
				$lon = $waypointlon;
			}

			if ($north === null || $waypointlat > $north) {
				$north = $waypointlat;
			}
			if ($south === null || $waypointlat < $south) {
				$south = $waypointlat;
			}
			if ($east === null || $waypointlon > $east) {
				$east = $waypointlon;
			}
			if ($west === null || $waypointlon < $west) {
				$west = $waypointlon;
			}
		}

		$trackNameList = trim($trackNameList, ',').']';
		if ($date_begin === null) {
			$date_begin = '';
		} else {
			$date_begin = $date_begin->format('Y-m-d H:i:s');
		}
		if ($date_end === null) {
			$date_end = '';
		} else {
			$date_end = $date_end->format('Y-m-d H:i:s');
		}
		$shortPointListTxt = '';
		foreach($shortPointList as $sp) {
			$shortPointListTxt .= sprintf('[%f, %f],', $sp[0], $sp[1]);
		}
		$shortPointListTxt = '[ '.trim($shortPointListTxt, ',').' ]';
		if ($north === null) {
			$north = 0;
		}
		if ($south === null) {
			$south = 0;
		}
		if ($east === null) {
			$east = 0;
		}
		if ($west === null) {
			$west = 0;
		}

		if ($max_elevation === null) {
			$max_elevation = '"???"';
		} else {
			$max_elevation = number_format($max_elevation, 2, '.', '');
		}
		if ($min_elevation === null) {
			$min_elevation = '"???"';
		} else {
			$min_elevation = number_format($min_elevation, 2, '.', '');
		}

		// we filter all segments by distance
		$distFilteredPointsBySegment = [];
		foreach ($pointsBySegment as $points) {
			$distFilteredPointsBySegment[] = $this->getDistanceFilteredPoints($points);
		}
		// and we get points with elevation and time for each segment
		$pointsWithElevationBySegment = [];
		$pointsWithTimeBySegment = [];
		foreach ($distFilteredPointsBySegment as $points) {
			$pointsWithTimeOneSegment = [];
			$pointsWithElevationOneSegment = [];
			foreach ($points as $point) {
				if (!empty($point->ele)) {
					$pointsWithElevationOneSegment[] = $point;
				}
				if (!empty($point->time)) {
					$pointsWithTimeOneSegment[] = $point;
				}
			}
			$pointsWithElevationBySegment[] = $pointsWithElevationOneSegment;
			$pointsWithTimeBySegment[] = $pointsWithTimeOneSegment;
		}
		// process elevation gain/loss
		$pos_elevation = 0;
		$neg_elevation = 0;
		foreach ($pointsWithElevationBySegment as $points) {
			$gainLoss = $this->getElevationGainLoss($points);
			$pos_elevation += $gainLoss[0];
			$neg_elevation += $gainLoss[1];
		}
		$pos_elevation = number_format($pos_elevation, 2, '.', '');
		$neg_elevation = number_format($neg_elevation, 2, '.', '');
		// process max speed from distance filtered points
		$maxSpeed = 0;
		foreach ($pointsWithTimeBySegment as $points) {
			$segmentMaxSpeed = $this->getMaxSpeed($points);
			if ($segmentMaxSpeed > $maxSpeed) {
				$maxSpeed = $segmentMaxSpeed;
			}
		}

		$result = sprintf('[%s, %s, "%s", "%s", %.3f, %s, "%s", "%s", %s, %.2f, %s, %s, %s, %.2f, %s, %s, %s, %.6f, %.6f, %.6f, %.6f, %s, %s, "%s", "%s", %.2f]',
			$lat,
			$lon,
			$gpx_relative_dir,
			$name,
			$total_distance,
			$total_duration,
			$date_begin,
			$date_end,
			$pos_elevation,
			$neg_elevation,
			$min_elevation,
			$max_elevation,
			$maxSpeed,
			$avg_speed,
			$moving_time,
			$stopped_time,
			$moving_avg_speed,
			$north,
			$south,
			$east,
			$west,
			$shortPointListTxt,
			$trackNameList,
			str_replace('"', "'", $linkurl),
			str_replace('"', "'", $linktext),
			$moving_pace
		);
		return $result;
	}

	private function getDistanceFilteredPoints($points) {
		$DISTANCE_THRESHOLD = 10;

		$distFilteredPoints = [];
		if (count($points) > 0) {
			$distFilteredPoints[] = $points[0];
			$lastPoint = $points[0];
			foreach ($points as $point) {
				if ($this->distance($lastPoint, $point) >= $DISTANCE_THRESHOLD) {
					$distFilteredPoints[] = $point;
					$lastPoint = $point;
				}
			}
		}

		return $distFilteredPoints;
	}

	private function getMaxSpeed($points) {
		$maxSpeed = 0;

		if (count($points) > 0) {
			$lastPoint = $points[0];
			$lastTime = new DateTime($lastPoint->time);
			foreach ($points as $point) {
				$time = new DateTime($point->time);
				$timeDelta = abs($lastTime->getTimestamp() - $time->getTimestamp());
				if (!is_null($point['lat']) && !is_null($point['lon']) && !is_null($lastPoint['lat']) && !is_null($lastPoint['lon'])
					&& $timeDelta > 0) {
					$distance = $this->distance($point, $lastPoint);
					$speed = $distance / $timeDelta;
					$speed = $speed / 1000;
					$speed = $speed * 3600;
					if ($speed > $maxSpeed) {
						$maxSpeed = $speed;
					}
				}
				$lastTime = $time;
				$lastPoint = $point;
			}
		}

		return $maxSpeed;
	}

	/**
	 * inspired by https://www.gpsvisualizer.com/tutorials/elevation_gain.html
	 */
	private function getElevationGainLoss($points) {
		$ELEVATION_THRESHOLD = 6;
		$gain = 0;
		$loss = 0;

		// then calculate elevation gain with elevation threshold
		if (count($points) > 0) {
			$validPoint = $points[0];
			foreach ($points as $point) {
				$deniv = floatval($point->ele) - floatval($validPoint->ele);
				if ($deniv >= $ELEVATION_THRESHOLD) {
					$gain += $deniv;
					$validPoint = $point;
				} else if (-$deniv >= $ELEVATION_THRESHOLD) {
					$loss -= $deniv;
					$validPoint = $point;
				}
			}
		}

		return [$gain, $loss];
	}

	/**
	 * get list of geolocated pictures in $subfolder with coordinates
	 * first copy the pics to a temp dir
	 * then get the pic list and coords with gpsbabel
	 *
	 * @param string $userId
	 * @param string $subfolder
	 * @param bool $recursive
	 * @return array
	 * @throws Exception
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws NoUserException
	 */
	public function getGeoPicsFromFolder(string $userId, string $subfolder, bool $recursive = false, int $directoryId): array {
		if (!function_exists('exif_read_data')) {
			return [];
		}
		$userFolder = $this->root->getUserFolder($userId);

		$pictures = [];

		$subfolder = str_replace(['../', '..\\'], '', $subfolder);
		$subfolder_path = $userFolder->get($subfolder)->getPath();
		$userfolder_path = $userFolder->getPath();
		$qb = $this->dbconnection->getQueryBuilder();

		$imagickAvailable = class_exists('Imagick');

		$optionValues = $this->getSharedMountedOptionValue($userId);
		$sharedAllowed = $optionValues['sharedAllowed'];
		$mountedAllowed = $optionValues['mountedAllowed'];

		// get picture files
		$picfiles = [];
		if ($recursive) {
			$picfiles = $this->searchFilesWithExt($userFolder->get($subfolder), $sharedAllowed, $mountedAllowed, ['.jpg']);
		} else {
			foreach ($userFolder->get($subfolder)->search('.jpg') as $picfile) {
				if ($picfile->getType() === \OCP\Files\FileInfo::TYPE_FILE
					&& dirname($picfile->getPath()) === $subfolder_path
					&& (
						$this->toolsService->endswith($picfile->getName(), '.jpg')
						|| $this->toolsService->endswith($picfile->getName(), '.JPG')
					)
				) {
					$picfiles[] = $picfile;
				}
			}
		}
		// get list of paths to manage deletion of absent files
		$picpaths = [];
		foreach ($picfiles as $picfile) {
			$pic_relative_path = str_replace($userfolder_path, '', $picfile->getPath());
			$pic_relative_path = rtrim($pic_relative_path, '/');
			$pic_relative_path = str_replace('//', '/', $pic_relative_path);
			$picpaths[] = $pic_relative_path;
		}

		$dbToDelete = [];
		// get what's in the DB
		$dbPicsWithCoords = [];
		$qb->select('path', 'contenthash')
			->from('gpxpod_pictures', 'p')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNotNull('lat')
			)
			->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder.'%', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();

		while ($row = $req->fetch()) {
			$dbPicsWithCoords[$row['path']] = $row['contenthash'];
			if ($recursive) {
				if (!in_array($row['path'], $picpaths)) {
					$dbToDelete[] = $row['path'];
				}
			} else {
				if (dirname($row['path']) === $subfolder
					&& !in_array($row['path'], $picpaths)
				) {
					$dbToDelete[] = $row['path'];
				}
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// get non-geotagged pictures
		$dbPicsWithoutCoords = [];
		$qb->select('path', 'contenthash')
			->from('gpxpod_pictures', 'p')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNull('lat')
			)
			->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder.'%', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();

		$gpxs_in_db = [];
		while ($row = $req->fetch()) {
			$dbPicsWithoutCoords[$row['path']] = $row['contenthash'];
			if ($recursive) {
				if (!in_array($row['path'], $picpaths)) {
					$dbToDelete[] = $row['path'];
				}
			} elseif (dirname($row['path']) === $subfolder
				&& !in_array($row['path'], $picpaths)) {
				$dbToDelete[] = $row['path'];
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// CHECK what is to be processed
		$picfilesToProcess = [];
		$newCRC = [];
		foreach ($picfiles as $pp) {
			$pic_relative_path = str_replace($userfolder_path, '', $pp->getPath());
			$pic_relative_path = rtrim($pic_relative_path, '/');
			$pic_relative_path = str_replace('//', '/', $pic_relative_path);
			$newCRC[$pic_relative_path] = $pp->getMTime().'.'.$pp->getSize();
			// if the file is not in the DB or if its content hash has changed
			if ((! array_key_exists($pic_relative_path, $dbPicsWithCoords))
				&& (! array_key_exists($pic_relative_path, $dbPicsWithoutCoords))
			) {
				$picfilesToProcess[] = $pp;
			} elseif (array_key_exists($pic_relative_path, $dbPicsWithCoords)
				&& $dbPicsWithCoords[$pic_relative_path] !== $newCRC[$pic_relative_path]
			) {
				$picfilesToProcess[] = $pp;
			} elseif (array_key_exists($pic_relative_path, $dbPicsWithoutCoords)
				&& $dbPicsWithoutCoords[$pic_relative_path] !== $newCRC[$pic_relative_path]
			) {
				$picfilesToProcess[] = $pp;
			} elseif (array_key_exists($pic_relative_path, $dbPicsWithoutCoords)) {
				//error_log('NOOOOT '.$pic_relative_path);
			}
		}

		// get coordinates of each picture file
		foreach ($picfilesToProcess as $picfile) {
			try {
				$lat = null;
				$lon = null;
				$dateTaken = null;

				// first we try with php exif function
				$filePath = $picfile->getStorage()->getLocalFile($picfile->getInternalPath());
				$exif = @exif_read_data($filePath, 'GPS,EXIF', true);
				if (    isset($exif['GPS'])
					&& isset($exif['GPS']['GPSLongitude'])
					&& isset($exif['GPS']['GPSLatitude'])
					&& isset($exif['GPS']['GPSLatitudeRef'])
					&& isset($exif['GPS']['GPSLongitudeRef'])
				) {
					$lon = $this->conversionService->getDecimalCoords($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
					$lat = $this->conversionService->getDecimalCoords($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
					// then get date
					if (isset($exif['EXIF']) && isset($exif['EXIF']['DateTimeOriginal'])) {
						$dateTaken = strtotime($exif['EXIF']['DateTimeOriginal']);
					}
				}
				// if no lat/lng were found, we try with imagick if available
				if ($lat === null && $lon === null && $imagickAvailable) {
					$pfile = $picfile->fopen('r');
					$img = new \Imagick();
					$img->readImageFile($pfile);
					$allGpsProp = $img->getImageProperties('exif:GPS*');
					if (    isset($allGpsProp['exif:GPSLatitude'])
						&& isset($allGpsProp['exif:GPSLongitude'])
						&& isset($allGpsProp['exif:GPSLatitudeRef'])
						&& isset($allGpsProp['exif:GPSLongitudeRef'])
					) {
						$lon = $this->conversionService->getDecimalCoords(explode(', ', $allGpsProp['exif:GPSLongitude']), $allGpsProp['exif:GPSLongitudeRef']);
						$lat = $this->conversionService->getDecimalCoords(explode(', ', $allGpsProp['exif:GPSLatitude']), $allGpsProp['exif:GPSLatitudeRef']);
						// then get date
						$dateProp = $img->getImageProperties('exif:DateTimeOriginal');
						if (isset($dateProp['exif:DateTimeOriginal'])) {
							$dateTaken = strtotime($dateProp['exif:DateTimeOriginal']);
						}
					}
					fclose($pfile);
				}

				// insert/update the DB
				$pic_relative_path = str_replace($userfolder_path, '', $picfile->getPath());
				$pic_relative_path = rtrim($pic_relative_path, '/');
				$pic_relative_path = str_replace('//', '/', $pic_relative_path);

				if (! array_key_exists($pic_relative_path, $dbPicsWithCoords)
					&& ! array_key_exists($pic_relative_path, $dbPicsWithoutCoords)
				) {
					$qb->insert('gpxpod_pictures')
						->values([
							'user' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
							'path' => $qb->createNamedParameter($pic_relative_path, IQueryBuilder::PARAM_STR),
							'contenthash' => $qb->createNamedParameter($newCRC[$pic_relative_path], IQueryBuilder::PARAM_STR),
							'lat' => $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
							'lon' => $qb->createNamedParameter($lon, IQueryBuilder::PARAM_STR),
							'date_taken' => $qb->createNamedParameter($dateTaken, IQueryBuilder::PARAM_INT)
						]);
					$req = $qb->execute();
					$qb = $qb->resetQueryParts();
				} else {
					$qb->update('gpxpod_pictures');
					$qb->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR));
					$qb->set('lon', $qb->createNamedParameter($lon, IQueryBuilder::PARAM_STR));
					$qb->set('date_taken', $qb->createNamedParameter($dateTaken, IQueryBuilder::PARAM_INT));
					$qb->set('contenthash', $qb->createNamedParameter($newCRC[$pic_relative_path], IQueryBuilder::PARAM_STR));
					$qb->where(
						$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
					)
						->andWhere(
							$qb->expr()->eq('path', $qb->createNamedParameter($pic_relative_path, IQueryBuilder::PARAM_STR))
						);
					$req = $qb->execute();
					$qb = $qb->resetQueryParts();
				}
			}
			catch (\Exception $e) {
				$this->logger->error(
					'Exception in picture geolocation reading for file '.$picfile->getPath().' : '. $e->getMessage(),
					['app' => Application::APP_ID]
				);
			}
		}

		// build result data from DB
		$subfolder_sql = $subfolder;
		if ($subfolder === '') {
			$subfolder_sql = '/';
		}
		$qb->select('id', 'path', 'lat', 'lon', 'date_taken')
			->from('gpxpod_pictures', 'p')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNotNull('lat')
			)
			->andWhere(
				$qb->expr()->isNotNull('lon')
			)
			->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder_sql.'%', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			if ($recursive || dirname($row['path']) === $subfolder_sql) {
				// if the pic file exists
				if ($userFolder->nodeExists($row['path'])) {
					$ff = $userFolder->get($row['path']);
					// if it's a file, if shared files are allowed or it's not shared
					if (    $ff->getType() === \OCP\Files\FileInfo::TYPE_FILE
						&& ($sharedAllowed || !$ff->isShared())
					) {
						$fileId = $ff->getId();
						$pictures[(int) $row['id']] = [
							'id' => (int) $row['id'],
							'path' => $row['path'],
							'lng' => $row['lon'],
							'lat' => $row['lat'],
							'file_id' => $fileId,
							'date_taken' => $row['date_taken'] ?? 0,
							'directory_id' => $directoryId,
						];
					}
				}
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// delete absent files
		foreach ($dbToDelete as $path) {
			//error_log('I DELETE '.$path);
			$qb->delete('gpxpod_pictures')
				->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('path', $qb->createNamedParameter($path, IQueryBuilder::PARAM_STR))
				);
			$qb->execute();
			$qb = $qb->resetQueryParts();
		}

		return $pictures;
	}

	public function getSharedMountedOptionValue(string $userId): array {
		$ss = $this->config->getUserValue($userId, 'gpxpod', 'showshared', 'true');
		$sm = $this->config->getUserValue($userId, 'gpxpod', 'showmounted', 'true');
		$sharedAllowed = ($ss === 'true');
		$mountedAllowed = ($sm === 'true');
		return ['sharedAllowed' => $sharedAllowed, 'mountedAllowed' => $mountedAllowed];
	}

	/**
	 * return distance between these two gpx points in meters
	 * @param $p1
	 * @param $p2
	 * @return float
	 */
	public function distance($p1, $p2): float {
		$lat1 = (float) $p1['lat'];
		$long1 = (float) $p1['lon'];
		$lat2 = (float) $p2['lat'];
		$long2 = (float) $p2['lon'];

		if ($lat1 === $lat2 && $long1 === $long2) {
			return 0;
		}

		// Convert latitude and longitude to
		// spherical coordinates in radians.
		$degrees_to_radians = pi() / 180.0;

		// phi = 90 - latitude
		$phi1 = (90.0 - $lat1) * $degrees_to_radians;
		$phi2 = (90.0 - $lat2) * $degrees_to_radians;

		// theta = longitude
		$theta1 = $long1 * $degrees_to_radians;
		$theta2 = $long2 * $degrees_to_radians;

		// Compute spherical distance from spherical coordinates.

		// For two locations in spherical coordinates
		// (1, theta, phi) and (1, theta, phi)
		// cosine( arc length ) =
		//    sin phi sin phi' cos(theta-theta') + cos phi cos phi'
		// distance = rho * arc length

		$cos = (sin($phi1) * sin($phi2) * cos($theta1 - $theta2) + cos($phi1) * cos($phi2));
		// why some cosinus are > than 1 ?
		if ($cos > 1.0) {
			$cos = 1.0;
		}
		$arc = acos($cos);

		// Remember to multiply arc by the radius of the earth
		// in your favorite set of units to get length.
		return $arc * 6371000;
	}
}
