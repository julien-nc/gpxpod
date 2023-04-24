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
use Exception;
use OC\Files\Node\File;
use OC\User\NoUserException;
use OCA\GpxPod\Db\DirectoryMapper;
use OCA\GpxPod\Db\Track;
use OCA\GpxPod\Db\TrackMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IDBConnection;

use OCA\GpxPod\AppInfo\Application;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Throwable;

class ProcessService {

	private const DISTANCE_BETWEEN_SHORT_POINTS = 300;
	private const STOPPED_SPEED_THRESHOLD = 0.9;
	// pi() / 180.0
	private const DEGREES_TO_RADIANS = 0.017453292519943;

	public function __construct(private IDBConnection     $dbconnection,
								private LoggerInterface   $logger,
								private IConfig           $config,
								private ConversionService $conversionService,
								private DirectoryMapper   $directoryMapper,
								private TrackMapper       $trackMapper,
								private ToolsService      $toolsService,
								private IRootFolder       $root) {
	}

	/**
	 * recursively search files with given extensions (case insensitive)
	 *
	 * @param Node $folder
	 * @param bool $sharedAllowed
	 * @param bool $mountedAllowed
	 * @param array $extensions
	 * @return array|File[]
	 */
	public function searchFilesWithExt(Node $folder, bool $sharedAllowed, bool $mountedAllowed, array $extensions): array {
		$res = [];
		foreach ($folder->getDirectoryListing() as $node) {
			// top level files with matching ext
			if ($node instanceof File) {
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

	/**
	 * get marker string for each gpx file
	 * return an array indexed by trackname
	 *
	 * @param array $gpxsToProcess
	 * @param string $userId
	 * @return array
	 */
	public function getMarkersFromFiles(array $gpxsToProcess, string $userId): array {
		$result = [];
		foreach ($gpxsToProcess as $gpxfile) {
			$marker = $this->getMarkerFromFile($gpxfile, $userId);
			if ($marker !== null) {
				$result[$gpxfile->getPath()] = $marker;
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
					$newCRC[$gpx_relative_path], json_encode($marker)
				);
			} else {
				$trackId = $dbTrackByPath[$gpx_relative_path]->getId();
				$this->trackMapper->updateTrack(
					$trackId, $userId,
					$newCRC[$gpx_relative_path], json_encode($marker)
				);
			}
		}
	}

	/**
	 * @param File $file
	 * @param string $userId
	 * @return array|null
	 * @throws NoUserException
	 * @throws NotPermittedException
	 * @throws LockedException
	 */
	public function getMarkerFromFile(File $file, string $userId): ?array {
		$name = $file->getName();

		// get path relative to user '/'
		$userFolder = $this->root->getUserFolder($userId);
		$userFolderPath = $userFolder->getPath();
		$dirname = dirname($file->getPath());
		$gpx_relative_dir = str_replace($userFolderPath, '', $dirname);
		if ($gpx_relative_dir !== '') {
			$gpx_relative_dir = rtrim($gpx_relative_dir, '/');
			$gpx_relative_dir = str_replace('//', '/', $gpx_relative_dir);
		} else {
			$gpx_relative_dir = '/';
		}

		$gpxContent = $file->getContent();
		$gpxContent = $this->toolsService->sanitizeGpxContent($gpxContent);

		$trackMarkerLat = null;
		$trackMarkerLon = null;
		$totalDistance = 0;
		$dateBegin = null;
		$dateEnd = null;

		$minElevation = null;
		$maxElevation = null;

		$averageSpeed = null;
		$movingTime = 0;
		$movingDistance = 0;
		$stoppedDistance = 0;
		$stoppedTime = 0;
		$north = null;
		$south = null;
		$east = null;
		$west = null;
		$shortPointList = [];
		$lastShortPoint = null;
		$trackNameList = [];
		$linkUrl = '';
		$linkText = '';

		$pointsBySegment = [];

		try {
			// TODO avoid producing warnings as NC level 3 log lines with:
			// SimpleXMLElement::__construct(): namespace error : Namespace prefix XXX on XXX is not defined
			$gpx = new SimpleXMLElement($gpxContent);
		} catch (Exception | Throwable $e) {
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
			$linkUrl = $gpx->metadata->link['href'];
			if (!empty($gpx->metadata->link->text)) {
				$linkText = $gpx->metadata->link->text;
			}
		}

		// TRACKS
		foreach ($gpx->trk as $track) {
			if ($track->trkseg !== null && count($track->trkseg) > 0) {
				$trackname = str_replace("\n", '', $track->name);
				if (empty($trackname)) {
					$trackname = '';
				}
				$trackNameList[] = $trackname;
				foreach ($track->trkseg as $segment) {
					if ($segment->trkpt !== null && count($segment->trkpt) > 0) {
						$pointsBySegment[] = $segment->trkpt;
						$newValues = $this->processSegment($segment->trkpt,
							$trackMarkerLat, $trackMarkerLon,
							$dateBegin, $dateEnd, $totalDistance,
							$stoppedTime, $movingTime,
							$stoppedDistance, $movingDistance,
							$minElevation, $maxElevation,
							$north, $south, $east, $west,
							$lastShortPoint,
							$shortPointList
						);
						[
							$trackMarkerLat, $trackMarkerLon,
							$dateBegin, $dateEnd, $totalDistance,
							$stoppedTime, $movingTime,
							$stoppedDistance, $movingDistance,
							$minElevation, $maxElevation,
							$north, $south, $east, $west,
							$lastShortPoint,
						] = $newValues;
					}
				}
			}
		}

		# ROUTES
		foreach ($gpx->rte as $route) {
			if ($route->rtept !== null && count($route->rtept) > 0) {
				$routename = str_replace("\n", '', $route->name);
				if (empty($routename)) {
					$routename = '';
				}
				$trackNameList[] = $routename;
				$pointsBySegment[] = $route->rtept;

				$newValues = $this->processSegment($route->rtept,
					$trackMarkerLat, $trackMarkerLon,
					$dateBegin, $dateEnd, $totalDistance,
					$stoppedTime, $movingTime,
					$stoppedDistance, $movingDistance,
					$minElevation, $maxElevation,
					$north, $south, $east, $west,
					$lastShortPoint,
					$shortPointList
				);
				[
					$trackMarkerLat, $trackMarkerLon,
					$dateBegin, $dateEnd, $totalDistance,
					$stoppedTime, $movingTime,
					$stoppedDistance, $movingDistance,
					$minElevation, $maxElevation,
					$north, $south, $east, $west,
					$lastShortPoint,
				] = $newValues;
			}
		}

		# TOTAL STATS : duration, avg speed, avg_moving_speed
		if ($dateEnd !== null && $dateBegin !== null) {
			$totalDuration = abs($dateEnd->getTimestamp() - $dateBegin->getTimestamp());
			if ($totalDuration === 0) {
				$averageSpeed = 0;
			} else {
				$averageSpeed = $totalDistance / $totalDuration;
				$averageSpeed = $averageSpeed / 1000;
				$averageSpeed = $averageSpeed * 3600;
			}
		} else {
			$totalDuration = 0;
		}

		// determination of real moving average speed from moving time
		$movingAverageSpeed = 0;
		$movingPace = 0;
		if ($movingTime > 0) {
			$movingAverageSpeed = $totalDistance / $movingTime;
			$movingAverageSpeed = $movingAverageSpeed / 1000;
			$movingAverageSpeed = $movingAverageSpeed * 3600;
			// pace in minutes/km
			$movingPace = $movingTime / $totalDistance;
			$movingPace = $movingPace / 60;
			$movingPace = $movingPace * 1000;
		}

		# WAYPOINTS
		foreach ($gpx->wpt as $waypoint) {
			$shortPointList[] = [
				$waypoint['lat'],
				$waypoint['lon'],
			];

			$waypointLat = (float) $waypoint['lat'];
			$waypointLon = (float) $waypoint['lon'];

			if ($trackMarkerLat === null || $trackMarkerLon === null) {
				$trackMarkerLat = $waypointLat;
				$trackMarkerLon = $waypointLon;
			}

			if ($north === null || $waypointLat > $north) {
				$north = $waypointLat;
			}
			if ($south === null || $waypointLat < $south) {
				$south = $waypointLat;
			}
			if ($east === null || $waypointLon > $east) {
				$east = $waypointLon;
			}
			if ($west === null || $waypointLon < $west) {
				$west = $waypointLon;
			}
		}

		if ($dateBegin !== null) {
			$dateBegin = $dateBegin->getTimestamp();
		}
		if ($dateEnd !== null) {
			$dateEnd = $dateEnd->getTimestamp();
		}
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
		$positiveElevation = 0;
		$negativeElevation = 0;
		foreach ($pointsWithElevationBySegment as $points) {
			$gainLoss = $this->getElevationGainLoss($points);
			$positiveElevation += $gainLoss[0];
			$negativeElevation += $gainLoss[1];
		}
		// process max speed from distance filtered points
		$maxSpeed = 0;
		foreach ($pointsWithTimeBySegment as $points) {
			$segmentMaxSpeed = $this->getMaxSpeed($points);
			if ($segmentMaxSpeed > $maxSpeed) {
				$maxSpeed = $segmentMaxSpeed;
			}
		}

		return [
			'lat' => $trackMarkerLat,
			'lon' => $trackMarkerLon,
			'folder' => $gpx_relative_dir,
			'name' => $name,
			'total_distance' => $totalDistance,
			'total_duration' => $totalDuration,
			'date_begin' => $dateBegin,
			'date_end' => $dateEnd,
			'positive_elevation_gain' => $positiveElevation,
			'negative_elevation_gain' => $negativeElevation,
			'min_elevation' => $minElevation,
			'max_elevation' => $maxElevation,
			'max_speed' => $maxSpeed,
			'average_speed' => $averageSpeed,
			'moving_time' => $movingTime,
			'stopped_time' => $stoppedTime,
			'moving_average_speed' => $movingAverageSpeed,
			'north' => $north,
			'south' => $south,
			'east' => $east,
			'west' => $west,
			'short_point_list' => $shortPointList,
			'track_name_list' => $trackNameList,
			'link_url' => $linkUrl,
			'link_text' => $linkText,
			'moving_pace' => $movingPace,
		];
	}

	/**
	 * @param SimpleXMLElement $points
	 * @return array
	 */
	private function getPointsWithCoordinates(SimpleXMLElement $points): array {
		$pointsWithCoords = [];
		foreach ($points as $point) {
			if ($point !== null && !empty($point['lat']) && !empty($point['lon'])) {
				$pointsWithCoords[] = $point;
			}
		}
		return $pointsWithCoords;
	}

	private function processSegment(SimpleXMLElement $points, ?float $trackMarkerLat, ?float $trackMarkerLon,
									?DateTime $dateBegin, ?DateTime $dateEnd, float $totalDistance,
									int $stoppedTime, int $movingTime,
									float $stoppedDistance, float $movingDistance,
									?float $minElevation, ?float $maxElevation,
									?float $north, ?float $south, ?float $east, ?float $west,
									?SimpleXMLElement $lastShortPoint,
									array &$shortPointList): array {
		// only keep points with coordinates
		$pointsWithCoords = $this->getPointsWithCoordinates($points);

		if (count($pointsWithCoords) > 0) {
			$firstPointLat = (float) $pointsWithCoords[0]['lat'];
			$firstPointLon = (float) $pointsWithCoords[0]['lon'];
			// init NSEW if necessary, set it with our first point
			if ($north === null) {
				$north = $firstPointLat;
				$south = $firstPointLat;
				$east = $firstPointLon;
				$west = $firstPointLon;
			}
			// init marker latLng if necessary
			if ($trackMarkerLat === null) {
				$trackMarkerLat = $firstPointLat;
				$trackMarkerLon = $firstPointLon;
			}
			// use our first point in the short point list
			$shortPointList[] = [$firstPointLat, $firstPointLon];
			$lastShortPoint = $pointsWithCoords[0];

			// find the first segment point with date and use it to initialize dateBegin
			foreach ($pointsWithCoords as $point) {
				if (!empty($point->time)) {
					try {
						$firstTime = new DateTime($point->time);
						if ($dateBegin === null || $firstTime < $dateBegin) {
							$dateBegin = $firstTime;
						}
					} catch (Exception | Throwable $e) {
					}
				}
			}

			// compute stats from single point info
			foreach ($pointsWithCoords as $point) {
				// coordinate-related stuff
				$pointLat = (float) $point['lat'];
				$pointLon = (float) $point['lon'];
				if ($pointLat > $north) {
					$north = $pointLat;
				}
				if ($pointLat < $south) {
					$south = $pointLat;
				}
				if ($pointLon > $east) {
					$east = $pointLon;
				}
				if ($pointLon < $west) {
					$west = $pointLon;
				}
				// if the point is more than X meters far from the last in shortPointList: we add it
				if ($this->distance((float)$lastShortPoint['lat'], (float)$lastShortPoint['lon'], $pointLat, $pointLon) > self::DISTANCE_BETWEEN_SHORT_POINTS) {
					$shortPointList[] = [$pointLat, $pointLon];
					$lastShortPoint = $point;
				}

				// elevation-related stuff
				if (empty($point->ele)) {
					$pointElevation = null;
				} else {
					$pointElevation = (float) $point->ele;
				}
				if ($pointElevation !== null && ($minElevation === null || $pointElevation < $minElevation)) {
					$minElevation = $pointElevation;
				}
				if ($pointElevation !== null && ($maxElevation === null || $pointElevation > $maxElevation)) {
					$maxElevation = $pointElevation;
				}
			}

			// compute stats that require point pairs
			// init values with first point
			$previousPoint = $pointsWithCoords[0];
			if (!empty($previousPoint->time)) {
				try {
					$previousTime = new DateTime($previousPoint->time);
				} catch (Exception | Throwable $e) {
					$previousTime = null;
				}
			} else {
				$previousTime = null;
			}
			// used later to update file end date
			$lastValidTime = $previousTime;

			for ($i = 1; $i < count($pointsWithCoords); $i++) {
				$point = $pointsWithCoords[$i];
				$pointLat = (float) $point['lat'];
				$pointLon = (float) $point['lon'];
				// distance-related stuff
				$distanceToPrevious = $this->distance((float)$previousPoint['lat'], (float)$previousPoint['lon'], $pointLat, $pointLon);
				$totalDistance += $distanceToPrevious;

				// time-related stuff
				if (empty($point->time)) {
					$pointTime = null;
				} else {
					try {
						$pointTime = new DateTime($point->time);
						$lastValidTime = $pointTime;
					} catch (Exception | Throwable $e) {
						$pointTime = null;
					}
				}

				if ($pointTime !== null && $previousTime !== null) {
					$t = abs($previousTime->getTimestamp() - $pointTime->getTimestamp());

					$speed = 0;
					if ($t > 0) {
						$speed = $distanceToPrevious / $t;
						$speed = $speed / 1000;
						$speed = $speed * 3600;
					}

					if ($speed <= self::STOPPED_SPEED_THRESHOLD) {
						$stoppedTime += $t;
						$stoppedDistance += $distanceToPrevious;
					} else {
						$movingTime += $t;
						$movingDistance += $distanceToPrevious;
					}
				}

				$previousPoint = $point;
				$previousTime = $pointTime;
			}

			if ($lastValidTime !== null && ($dateEnd === null || $lastValidTime > $dateEnd)) {
				$dateEnd = $lastValidTime;
			}
		}

		return [
			$trackMarkerLat, $trackMarkerLon,
			$dateBegin, $dateEnd, $totalDistance,
			$stoppedTime, $movingTime,
			$stoppedDistance, $movingDistance,
			$minElevation, $maxElevation,
			$north, $south, $east, $west,
			$lastShortPoint,
		];
	}

	/**
	 * @param SimpleXMLElement $points
	 * @return array
	 */
	private function getDistanceFilteredPoints(SimpleXMLElement $points): array {
		$DISTANCE_THRESHOLD = 10;

		$pointsWithCoordinates = $this->getPointsWithCoordinates($points);

		$distFilteredPoints = [];
		if (count($pointsWithCoordinates) > 0) {
			$distFilteredPoints[] = $pointsWithCoordinates[0];
			$lastPoint = $pointsWithCoordinates[0];
			foreach ($pointsWithCoordinates as $point) {
				if ($this->distanceBetweenGpxPoints($lastPoint, $point) >= $DISTANCE_THRESHOLD) {
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
			try {
				$lastTime = new DateTime($lastPoint->time);
			} catch (Exception | Throwable $e) {
				$lastTime = null;
			}
			foreach ($points as $point) {
				try {
					$time = new DateTime($point->time);
				} catch (Exception | Throwable $e) {
					$time = null;
				}
				if ($time === null || $lastTime === null) {
					continue;
				}
				$timeDelta = abs($lastTime->getTimestamp() - $time->getTimestamp());
				if (!is_null($point['lat']) && !is_null($point['lon']) && !is_null($lastPoint['lat']) && !is_null($lastPoint['lon'])
					&& $timeDelta > 0) {
					$distance = $this->distanceBetweenGpxPoints($point, $lastPoint);
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
	 * @param int $directoryId
	 * @param bool $recursive
	 * @return array
	 * @throws InvalidPathException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function getGeoPicsFromFolder(string $userId, string $subfolder, int $directoryId, bool $recursive = false): array {
		if (!function_exists('exif_read_data') && !class_exists('\IMagick')) {
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
			$picfiles = $this->searchFilesWithExt($userFolder->get($subfolder), $sharedAllowed, $mountedAllowed, ['.jpg', '.jpeg']);
		} else {
			foreach ($userFolder->get($subfolder)->search('.jpg') as $picfile) {
				if ($picfile instanceof File
					&& dirname($picfile->getPath()) === $subfolder_path
					&& preg_match('/\.jpg$/i', $picfile->getName())
				) {
					$picfiles[] = $picfile;
				}
			}
			foreach ($userFolder->get($subfolder)->search('.jpeg') as $picfile) {
				if ($picfile instanceof File
					&& dirname($picfile->getPath()) === $subfolder_path
					&& preg_match('/\.jpeg$/i', $picfile->getName())
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
			);
		if ($recursive) {
			$qb->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder.'%', IQueryBuilder::PARAM_STR))
			);
		} else {
			$qb->andWhere(
				$qb->expr()->eq('directory_id', $qb->createNamedParameter($directoryId, IQueryBuilder::PARAM_INT))
			);
		}
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
			);
		if ($recursive) {
			$qb->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder.'%', IQueryBuilder::PARAM_STR))
			);
		} else {
			$qb->andWhere(
				$qb->expr()->eq('directory_id', $qb->createNamedParameter($directoryId, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->execute();

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
				$direction = null;

				// first we try with php exif function
				$exif = exif_read_data(
					'data://image/jpeg;base64,' . base64_encode($picfile->getContent()),
					'GPS,EXIF',
					true
				);
				if (isset(
						$exif['GPS'],
						$exif['GPS']['GPSLongitude'],
						$exif['GPS']['GPSLatitude'],
						$exif['GPS']['GPSLatitudeRef'],
						$exif['GPS']['GPSLongitudeRef']
				)) {
					$lon = $this->conversionService->getDecimalCoords($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
					$lat = $this->conversionService->getDecimalCoords($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
					// then get date
					if (isset($exif['EXIF'], $exif['EXIF']['DateTimeOriginal'])) {
						$dateTaken = strtotime($exif['EXIF']['DateTimeOriginal']);
					}
					// then get direction
					if (isset($exif['GPS']['GPSImgDirection'])) {
						try {
							if (str_contains($exif['GPS']['GPSImgDirection'], '/')) {
								$spl = explode('/', $exif['GPS']['GPSImgDirection']);
								$direction = (int)(((int)$spl[0]) / ((int)$spl[1]));
							} else {
								$direction = (int)$exif['GPS']['GPSImgDirection'];
							}
						} catch (Throwable $e) {
							$this->logger->debug(
								'Error when getting photo direction of '.$picfile->getPath().' : '. $e->getMessage(),
								['app' => Application::APP_ID]
							);
						}
					}
				}
				// if no lat/lng were found, we try with imagick if available
				if ($lat === null && $lon === null && $imagickAvailable) {
					$pfile = $picfile->fopen('r');
					$img = new \Imagick();
					$img->readImageFile($pfile);
					$allGpsProp = $img->getImageProperties('exif:GPS*');
					if (isset(
						$allGpsProp['exif:GPSLatitude'],
						$allGpsProp['exif:GPSLongitude'],
						$allGpsProp['exif:GPSLatitudeRef'],
						$allGpsProp['exif:GPSLongitudeRef']
					)) {
						$lon = $this->conversionService->getDecimalCoords(explode(', ', $allGpsProp['exif:GPSLongitude']), $allGpsProp['exif:GPSLongitudeRef']);
						$lat = $this->conversionService->getDecimalCoords(explode(', ', $allGpsProp['exif:GPSLatitude']), $allGpsProp['exif:GPSLatitudeRef']);
						// then get date
						$dateProp = $img->getImageProperties('exif:DateTimeOriginal');
						if (isset($dateProp['exif:DateTimeOriginal'])) {
							$dateTaken = strtotime($dateProp['exif:DateTimeOriginal']);
						}
						// then get direction
						if (isset($allGpsProp['exif:GPSImgDirection'])) {
							try {
								if (str_contains($allGpsProp['exif:GPSImgDirection'], '/')) {
									$spl = explode('/', $allGpsProp['exif:GPSImgDirection']);
									$direction = (int)(((int)$spl[0]) / ((int)$spl[1]));
								} else {
									$direction = (int) $allGpsProp['exif:GPSImgDirection'];
								}
							} catch (Throwable $e) {
								$this->logger->debug(
									'Error when getting photo direction of '.$picfile->getPath().' : '. $e->getMessage(),
									['app' => Application::APP_ID]
								);
							}
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
							'date_taken' => $qb->createNamedParameter($dateTaken, IQueryBuilder::PARAM_INT),
							'direction' => $qb->createNamedParameter($direction, IQueryBuilder::PARAM_INT),
							'directory_id' => $qb->createNamedParameter($directoryId, IQueryBuilder::PARAM_INT)
						]);
					$req = $qb->execute();
					$qb = $qb->resetQueryParts();
				} else {
					$qb->update('gpxpod_pictures')
						->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR))
						->set('lon', $qb->createNamedParameter($lon, IQueryBuilder::PARAM_STR))
						->set('date_taken', $qb->createNamedParameter($dateTaken, IQueryBuilder::PARAM_INT))
						->set('direction', $qb->createNamedParameter($direction, IQueryBuilder::PARAM_INT))
						->set('contenthash', $qb->createNamedParameter($newCRC[$pic_relative_path], IQueryBuilder::PARAM_STR))
						->where(
							$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('path', $qb->createNamedParameter($pic_relative_path, IQueryBuilder::PARAM_STR))
						);
					$req = $qb->execute();
					$qb = $qb->resetQueryParts();
				}
			} catch (Exception | Throwable $e) {
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
		$qb->select('id', 'path', 'lat', 'lon', 'date_taken', 'direction')
			->from('gpxpod_pictures', 'p')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNotNull('lat')
			)
			->andWhere(
				$qb->expr()->isNotNull('lon')
			);
		if ($recursive) {
			$qb->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder_sql.'%', IQueryBuilder::PARAM_STR))
			);
		} else {
			$qb->andWhere(
				$qb->expr()->eq('directory_id', $qb->createNamedParameter($directoryId, IQueryBuilder::PARAM_INT))
			);
		}
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			if ($recursive || dirname($row['path']) === $subfolder_sql) {
				// if the pic file exists
				if ($userFolder->nodeExists($row['path'])) {
					$ff = $userFolder->get($row['path']);
					// if it's a file, if shared files are allowed or it's not shared
					if (    $ff instanceof File
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
							'direction' => $row['direction'],
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

	public function distanceBetweenGpxPoints(SimpleXMLElement $p1, SimpleXMLElement $p2): float	{
		$lat1 = (float) $p1['lat'];
		$lon1 = (float) $p1['lon'];
		$lat2 = (float) $p2['lat'];
		$lon2 = (float) $p2['lon'];

		return $this->distance($lat1, $lon1, $lat2, $lon2);
	}

	/**
	 * Compute distance between 2 geo points in meters
	 * @param float $lat1
	 * @param float $lon1
	 * @param float $lat2
	 * @param float $lon2
	 * @return float
	 */
	public function distance(float $lat1, float $lon1, float $lat2, float $lon2): float {
		if ($lat1 === $lat2 && $lon1 === $lon2) {
			return 0;
		}

		// Convert latitude and longitude to
		// spherical coordinates in radians.

		// phi = 90 - latitude
		$phi1 = (90.0 - $lat1) * self::DEGREES_TO_RADIANS;
		$phi2 = (90.0 - $lat2) * self::DEGREES_TO_RADIANS;

		// theta = longitude
		$theta1 = $lon1 * self::DEGREES_TO_RADIANS;
		$theta2 = $lon2 * self::DEGREES_TO_RADIANS;

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

	/**
	 * @param string $userId
	 * @param int $trackId
	 * @return bool
	 * @throws InvalidPathException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function deleteTrack(string $userId, int $trackId): bool {
		try {
			$track = $this->trackMapper->getTrackOfUser($trackId, $userId);
		} catch (DoesNotExistException $e) {
			return false;
		}
		try {
			$dir = $this->directoryMapper->getDirectoryOfUser($track->getDirectoryId(), $userId);
		} catch (DoesNotExistException $e) {
			return false;
		}
		$userFolder = $this->root->getUserFolder($userId);
		$cleanPath = str_replace(['../', '..\\'], '', $track->getTrackpath());
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File && $file->isDeletable()) {
				$file->delete();
				$this->trackMapper->delete($track);
				return true;
			}
		}
		return false;
	}
}
