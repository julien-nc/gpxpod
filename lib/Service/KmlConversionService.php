<?php

/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2023
 */

namespace OCA\GpxPod\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use Exception;
use lsolesen\pel\PelDataWindow;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelTiff;
use OC\User\NoUserException;
use OCA\GpxPod\Db\Directory;
use OCA\GpxPod\Db\PictureMapper;
use OCA\GpxPod\Db\TrackMapper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Lock\LockedException;
use phpGPX\Models\Point;
use phpGPX\phpGPX;
use Throwable;
use ZipArchive;

require_once __DIR__ . '/../../vendor/autoload.php';

class KmlConversionService {

	public function __construct(private ToolsService $toolsService,
		private IRootFolder $root,
		private TrackMapper $trackMapper,
		private PictureMapper $pictureMapper) {
	}

	/**
	 * find the first kml file at the top level of the kmz zip archive
	 * and converts it
	 *
	 * @param string $kmzContent
	 * @param string $kmzFileName
	 * @param Folder $kmlFolder
	 * @return string
	 * @throws \DOMException
	 */
	public function kmzToGpx(string $kmzContent, string $kmzFileName, Folder $kmlFolder): string {
		$tempFile = tempnam(sys_get_temp_dir(), 'gpxpod_kmz_');
		file_put_contents($tempFile, $kmzContent);
		$zip = new ZipArchive();
		$zip->open($tempFile);

		for ($i = 0; $i < $zip->count(); $i++) {
			$filePath = $zip->getNameIndex($i);
			$fileName = basename($filePath);
			$ext = pathinfo($fileName, PATHINFO_EXTENSION);
			if ($ext === 'kml' && dirname($filePath) === '.') {
				$kmlContent = $zip->getFromIndex($i);
				$this->extractImages($zip, $kmlContent, $kmzFileName, $kmlFolder);
				$zip->close();
				return $this->kmlToGpx($kmlContent);
			}
		}
		$zip->close();
		throw new Exception('No kml file found in the kmz archive');
	}

	private function extractImages(ZipArchive $zip, string $kmlContent, string $kmzFileName, Folder $kmlFolder): void {
		// find photos and coords in kml
		$domKml = new DOMDocument();
		$domKml->loadXML($kmlContent, LIBXML_NOBLANKS);

		$photos = [];
		$photoOverlays = $domKml->getElementsByTagName('PhotoOverlay');
		for ($i = 0; $i < $photoOverlays->count(); $i++) {
			$photoOverlay = $photoOverlays->item($i);
			if ($photoOverlay instanceof DOMElement && $photoOverlay->hasChildNodes()) {
				$photo = $this->findPhotoInfo($photoOverlay);
				if ($photo !== null) {
					$photos[] = $photo;
				}
			}
		}

		// check if photo exists in the archive
		foreach ($photos as $i => $photo) {
			$index = $zip->locateName($photo['path']);
			if ($index !== false) {
				$targetPhotoName = preg_replace('/\.kmz$/i', '_' . $i . '.' . $photo['ext'], $kmzFileName);
				if (!$kmlFolder->nodeExists($targetPhotoName)) {
					// set the exif data
					try {
						$photoContent = $this->setPhotoCoordinates($zip->getFromIndex($index), $photo['lat'], $photo['lon']);
						$kmlFolder->newFile($targetPhotoName, $photoContent);
					} catch (Exception | Throwable $e) {
					}
				}
			}
		}
	}

	public function setPhotoCoordinates(string $photoContent, float $lat, float $lon): string {
		$data = new PelDataWindow($photoContent);
		$pelJpeg = new PelJpeg($data);

		$pelExif = $pelJpeg->getExif();
		if ($pelExif === null) {
			$pelExif = new PelExif();
			$pelJpeg->setExif($pelExif);
		}

		$pelTiff = $pelExif->getTiff();
		if ($pelTiff === null) {
			$pelTiff = new PelTiff();
			$pelExif->setTiff($pelTiff);
		}

		$pelIfd0 = $pelTiff->getIfd();
		if ($pelIfd0 === null) {
			$pelIfd0 = new PelIfd(PelIfd::IFD0);
			$pelTiff->setIfd($pelIfd0);
		}

		$pelSubIfdGps = new PelIfd(PelIfd::GPS);
		$pelIfd0->addSubIfd($pelSubIfdGps);

		$this->setGeolocation($pelSubIfdGps, $lat, $lon);

		return $pelJpeg->getBytes();
	}

	private function setGeolocation(PelIfd $pelSubIfdGps, float $latitudeDegreeDecimal, float $longitudeDegreeDecimal): void {
		$latitudeRef = ($latitudeDegreeDecimal >= 0) ? 'N' : 'S';
		$latitudeDegreeMinuteSecond = $this->degreeDecimalToDegreeMinuteSecond(abs($latitudeDegreeDecimal));
		$longitudeRef = ($longitudeDegreeDecimal >= 0) ? 'E' : 'W';
		$longitudeDegreeMinuteSecond = $this->degreeDecimalToDegreeMinuteSecond(abs($longitudeDegreeDecimal));

		$pelSubIfdGps->addEntry(new PelEntryAscii(PelTag::GPS_LATITUDE_REF, $latitudeRef));
		$pelSubIfdGps->addEntry(new PelEntryRational(
			PelTag::GPS_LATITUDE,
			[$latitudeDegreeMinuteSecond['degree'], 1],
			[$latitudeDegreeMinuteSecond['minute'], 1],
			[round($latitudeDegreeMinuteSecond['second'] * 1000), 1000]));
		$pelSubIfdGps->addEntry(new PelEntryAscii(PelTag::GPS_LONGITUDE_REF, $longitudeRef));
		$pelSubIfdGps->addEntry(new PelEntryRational(
			PelTag::GPS_LONGITUDE,
			[$longitudeDegreeMinuteSecond['degree'], 1],
			[$longitudeDegreeMinuteSecond['minute'], 1],
			[round($longitudeDegreeMinuteSecond['second'] * 1000), 1000]));
	}

	private function degreeDecimalToDegreeMinuteSecond(float $degreeDecimal): array {
		$degree = floor($degreeDecimal);
		$remainder = $degreeDecimal - $degree;
		$minute = floor($remainder * 60);
		$remainder = ($remainder * 60) - $minute;
		$second = $remainder * 60;
		return ['degree' => $degree, 'minute' => $minute, 'second' => $second];
	}

	public function findPhotoInfo(DOMElement $photoOverlay): ?array {
		// look for "> Point > coordinates" and "> Timestamp > when" and "> Icon > href"
		$coordinates = $photoOverlay->getElementsByTagName('coordinates');
		for ($j = 0; $j < $coordinates->count(); $j++) {
			$coords = $coordinates->item($j);
			if ($coords instanceof DOMElement
				&& $coords->textContent
				&& $coords->parentNode instanceof DOMElement
				&& $coords->parentNode->localName === 'Point') {
				$coordsText = str_replace(' ', '', $coords->textContent);
				$coordParts = explode(',', $coordsText);
				if (count($coordParts) > 1) {
					$lon = $coordParts[0];
					$lat = $coordParts[1];
					$ele = count($coordParts) > 2 ? $coordParts[2] : null;

					// > TimeStamp > when
					$whens = $photoOverlay->getElementsByTagName('when');
					for ($k = 0; $k < $whens->count(); $k++) {
						$when = $whens->item($k);
						if ($when instanceof DOMElement
							&& $when->textContent
							&& $when->parentNode instanceof DOMElement
							&& $when->parentNode->localName === 'TimeStamp') {
							$photoDate = $when->textContent;

							$hrefs = $photoOverlay->getElementsByTagName('href');
							for ($l = 0; $l < $hrefs->count(); $l++) {
								$href = $hrefs->item($l);
								if ($href instanceof DOMElement
									&& $href->textContent
									&& $href->parentNode instanceof DOMElement
									&& $href->parentNode->localName === 'Icon') {
									$photoZipPath = $href->textContent;
									$photoZipName = basename($photoZipPath);
									$ext = pathinfo($photoZipName, PATHINFO_EXTENSION);
									if (in_array($ext, ['jpg', 'jpeg', 'JPG', 'JPEG'])) {
										return [
											'lat' => (float) $lat,
											'lon' => (float) $lon,
											'ele' => $ele,
											'time' => $photoDate,
											'path' => $photoZipPath,
											'ext' => $ext,
										];
									}
								}
							}
						}
					}
				}
			}
		}
		return null;
	}

	/**
	 * @param string $kmlContent
	 * @return string
	 * @throws \DOMException
	 */
	public function kmlToGpx(string $kmlContent): string {
		$domKml = new DOMDocument();
		$domKml->loadXML($kmlContent, LIBXML_NOBLANKS);

		$domGpx = $this->toolsService->createDomGpxWithHeaders();
		$gpx = $domGpx->getElementsByTagName('gpx')->item(0);

		// placemarks
		$names = [];
		foreach ($domKml->getElementsByTagName('Placemark') as $placemark) {
			//name
			foreach ($placemark->getElementsByTagName('name') as $name) {
				$name = $name->nodeValue;
				//check if the key exists
				if (array_key_exists($name, $names)) {
					//increment the value
					++$names[$name];
					$name = $name." ({$names[$name]})";
				} else {
					$names[$name] = 0;
				}
			}
			//description
			foreach ($placemark->getElementsByTagName('description') as $description) {
				$description = $description->nodeValue;
			}
			foreach ($placemark->getElementsByTagName('Point') as $point) {
				foreach ($point->getElementsByTagName('coordinates') as $coordinates) {
					//add the marker
					$coordinate = $coordinates->nodeValue;
					$coordinate = str_replace(' ', '', $coordinate);
					$latlng = explode(',', $coordinate);

					if (count($latlng) > 1 && ($lat = $latlng[1]) && ($lng = $latlng[0])) {
						$gpxWaypoint = $gpx->appendChild($domGpx->createElement('wpt'));

						$gpxWaypoint->appendChild($domGpx->createAttribute('lat'))
							->appendChild($domGpx->createTextNode($lat));
						$gpxWaypoint->appendChild($domGpx->createAttribute('lon'))
							->appendChild($domGpx->createTextNode($lng));

						$gpxWaypoint->appendChild($domGpx->createElement('time'))
							->appendChild($domGpx->createTextNode($this->toolsService->utcdate()));
						$gpxWaypoint->appendChild($domGpx->createElement('name'))
							->appendChild($domGpx->createTextNode($name));
						$gpxWaypoint->appendChild($domGpx->createElement('desc'))
							->appendChild($domGpx->createTextNode($description));

						if (count($latlng) > 2) {
							$gpxWaypoint->appendChild($domGpx->createElement('ele'))
								->appendChild($domGpx->createTextNode($latlng[2]));
						}
					}
				}
			}
			foreach ($placemark->getElementsByTagName('Polygon') as $lineString) {
				$outbounds = $lineString->getElementsByTagName('outerBoundaryIs');
				foreach ($outbounds as $outbound) {
					foreach ($outbound->getElementsByTagName('coordinates') as $coordinates) {
						$gpxTrk = $gpx->appendChild($domGpx->createElement('trk'));
						$gpxTrk->appendChild($domGpx->createElement('name'))
							->appendChild($domGpx->createTextNode($name));

						$gpxTrkseg = $domGpx->createElement('trkseg');

						$coordinates = trim($coordinates->nodeValue);
						$coordinates = preg_split('/[\s\r\n]+/', $coordinates); //split the coords by new line
						foreach ($coordinates as $coordinate) {
							$latlng = explode(",", $coordinate);

							if (count($latlng) > 1 && ($lat = $latlng[1]) && ($lng = $latlng[0])) {
								$time = $this->toolsService->utcdate();
								$ele = count($latlng) > 2 ? $latlng[2] : null;
								$this->appendGpxPoint($domGpx, $gpxTrkseg, 'trkpt', $lat, $lng, $time, $ele);
							}
						}
						if ($gpxTrkseg->hasChildNodes()) {
							$gpxTrk->appendChild($gpxTrkseg);
						}
					}
				}
			}
			foreach ($placemark->getElementsByTagName('LineString') as $lineString) {
				foreach ($lineString->getElementsByTagName('coordinates') as $coordinates) {
					$gpxTrk = $gpx->appendChild($domGpx->createElement('trk'));
					$gpxTrk->appendChild($domGpx->createElement('name'))
						->appendChild($domGpx->createTextNode($name));

					$gpxTrkseg = $domGpx->createElement('trkseg');

					$coordinates = trim($coordinates->nodeValue);
					$coordinates = preg_split('/[\r\n]+/', $coordinates); //split the coords by new line
					foreach ($coordinates as $coordinate) {
						$latlng = explode(',', $coordinate);

						if (count($latlng) > 1 && ($lat = $latlng[1]) && ($lng = $latlng[0])) {
							$time = $this->toolsService->utcdate();
							$ele = count($latlng) > 2 ? $latlng[2] : null;
							$this->appendGpxPoint($domGpx, $gpxTrkseg, 'trkpt', $lat, $lng, $time, $ele);
						}
					}
					if ($gpxTrkseg->hasChildNodes()) {
						$gpxTrk->appendChild($gpxTrkseg);
					}
				}
			}
			// Placemark > MultiTrack > Track
			$placemarks = $domKml->getElementsByTagName('Placemark');
			for ($i = 0; $i < $placemarks->count(); $i++) {
				$placemark = $placemarks->item($i);
				if ($placemark instanceof DOMElement && $placemark->hasChildNodes()) {
					$gpxTrack = $domGpx->createElement('trk');

					/** @var \DOMNodeList $plChildren */
					$plChildren = $placemark->childNodes;
					for ($j = 0; $j < $plChildren->count(); $j++) {
						$plChild = $plChildren->item($j);
						if ($plChild instanceof DOMElement && $plChild->nodeName === 'MultiTrack') {
							$kmlTracks = $plChild->getElementsByTagName('Track');

							for ($k = 0; $k < $kmlTracks->count(); $k++) {
								$kmlTrack = $kmlTracks->item($k);
								if ($kmlTrack instanceof DOMElement && $kmlTrack->hasChildNodes()) {
									$gpxSegment = $domGpx->createElement('trkseg');

									$whens = $kmlTrack->getElementsByTagName('when');
									$coords = $kmlTrack->getElementsByTagName('coord');
									$extraData = $this->getExtraDataByName($kmlTrack);
									for ($l = 0; $l < $whens->count(); $l++) {
										$when = $whens->item($l);
										$coord = $coords->item($l);
										if ($when !== null && $when->textContent && $coord !== null && $coord->textContent) {
											$whenContent = $when->textContent;
											$coordContent = $coord->textContent;
											$coordParts = explode(' ', $coordContent);
											$partCount = count($coordParts);

											if ($partCount > 1) {
												$lon = $coordParts[0];
												$lat = $coordParts[1];
												$ele = $partCount > 2 ? $coordParts[2] : null;

												$gpxPoint = $this->appendGpxPoint($domGpx, $gpxSegment, 'trkpt', $lat, $lon, $whenContent, $ele);

												$this->appendExtensionsToPoint($domGpx, $gpxPoint, $l, $extraData);
											}
										}
									}
									// if segment has points
									if ($gpxSegment->hasChildNodes()) {
										$gpxTrack->appendChild($gpxSegment);
									}
								}
							}
						}
					}
					// if track has segments
					if ($gpxTrack->hasChildNodes()) {
						$gpx->appendChild($gpxTrack);
					}
				}
			}
		}

		return $domGpx->saveXML();
	}

	/**
	 * @param DOMDocument $dom
	 * @param DOMElement $gpxPoint
	 * @param int $valueIndex
	 * @param array $extensions
	 * @return DOMElement
	 * @throws \DOMException
	 */
	private function appendExtensionsToPoint(DOMDocument $dom, DOMElement $gpxPoint, int $valueIndex, array $extensions): DOMElement {
		$extensionsElement = $dom->createElement('extensions');
		foreach ($extensions as $name => $values) {
			if (isset($values[$valueIndex])) {
				$extensionsElement
					->appendChild($dom->createElement($name))
					->appendChild($dom->createTextNode($values[$valueIndex]));
			}
		}
		if ($extensionsElement->hasChildNodes()) {
			$gpxPoint->appendChild($extensionsElement);
		}
		return $gpxPoint;
	}

	/**
	 * @param DOMElement $kmlTrack
	 * @return array
	 */
	private function getExtraDataByName(DOMElement $kmlTrack): array {
		$result = [];

		// get additional data
		$simpleArrays = $kmlTrack->getElementsByTagName('SimpleArrayData');

		for ($i = 0; $i < $simpleArrays->count(); $i++) {
			$simpleArray = $simpleArrays->item($i);
			if ($simpleArray instanceof DOMElement && $simpleArray->hasChildNodes()
				&& $simpleArray->parentNode instanceof DOMElement
				&& $simpleArray->parentNode->localName === 'SchemaData'
				&& $simpleArray->parentNode->parentNode instanceof DOMElement
				&& $simpleArray->parentNode->parentNode->localName === 'ExtendedData') {
				$name = $simpleArray->getAttribute('name');
				if ($name) {
					$result[$name] = [];
					/** @var \DOMNodeList $values */
					$values = $simpleArray->childNodes;
					for ($j = 0; $j < $values->count(); $j++) {
						$value = $values->item($j);
						$result[$name][] = $value->textContent;
					}
				}
			}
		}

		return $result;
	}

	/**
	 * @param DOMDocument $dom
	 * @param DOMNode $gpxSegment
	 * @param string $tag
	 * @param string $lat
	 * @param string $lon
	 * @param string $time
	 * @param string|null $ele
	 * @return DOMElement
	 * @throws \DOMException
	 */
	private function appendGpxPoint(DOMDocument $dom, DOMNode $gpxSegment, string $tag, string $lat, string $lon, string $time, ?string $ele): DOMElement {
		$gpxPoint = $dom->createElement($tag);
		$gpxPoint = $gpxSegment->appendChild($gpxPoint);

		$gpxPoint->appendChild($dom->createAttribute('lat'))
			->appendChild($dom->createTextNode($lat));
		$gpxPoint->appendChild($dom->createAttribute('lon'))
			->appendChild($dom->createTextNode($lon));
		$gpxPoint->appendChild($dom->createElement('time'))
			->appendChild($dom->createTextNode($time));

		if ($ele !== null) {
			$gpxPoint->appendChild($dom->createElement('ele'))
				->appendChild($dom->createTextNode($ele));
		}
		return $gpxPoint;
	}

	/**
	 * @param string $userId
	 * @param Directory $dir
	 * @return string
	 * @throws \DOMException
	 */
	public function exportDirToKml(string $userId, Directory $dir): string {
		$kmlDom = $this->getDirectoryKmlDocument($userId, $dir);
		return $kmlDom->saveXML();
	}

	/**
	 * @param string $userId
	 * @param Directory $dir
	 * @return string
	 * @throws LockedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \DOMException
	 * @throws \OCP\DB\Exception
	 */
	public function exportDirToKmz(string $userId, Directory $dir): string {
		$kmlDoc = $this->getDirectoryKmlDocument($userId, $dir);

		$tempFile = tempnam(sys_get_temp_dir(), 'gpxpod_kmz_');
		$zip = new ZipArchive();
		$zip->open($tempFile, ZipArchive::OVERWRITE);

		// add photos to the kml content and to the archive
		$this->addPhotosToKmz($zip, $kmlDoc, $dir, $userId);

		// create a zip archive in a temp file
		$zip->addFromString('doc.kml', $kmlDoc->saveXML());
		$zip->close();
		$zipContent = file_get_contents($tempFile);

		return $zipContent;
	}

	/**
	 * @param ZipArchive $zip
	 * @param DOMDocument $kmlDoc
	 * @param Directory $dir
	 * @param string $userId
	 * @return void
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \DOMException
	 * @throws \OCP\DB\Exception
	 */
	public function addPhotosToKmz(ZipArchive $zip, DOMDocument $kmlDoc, Directory $dir, string $userId): void {
		$pics = $this->pictureMapper->getDirectoryTracksOfUser($userId, $dir->getId());

		$picFilesToAdd = [];

		$userFolder = $this->root->getUserFolder($userId);
		foreach ($pics as $pic) {
			$picPath = $pic->getPath();
			if ($userFolder->nodeExists($picPath)) {
				$picFile = $userFolder->get($picPath);
				if ($picFile instanceof File) {
					$picFilesToAdd[] = [
						'file' => $picFile,
						'db_pic' => $pic,
					];
				}
			}
		}

		if (count($picFilesToAdd) > 0) {
			$documents = $kmlDoc->getElementsByTagName('Document');
			if ($documents->length > 0) {
				$document = $documents->item(0);
				$folder = $document->appendChild($kmlDoc->createElement('Folder'));

				$zip->addEmptyDir('images');

				foreach ($picFilesToAdd as $pic) {
					$picFileName = $pic['file']->getName();
					$zip->addFromString('images/' . $picFileName, $pic['file']->getContent());

					$photoOverlay = $folder->appendChild($kmlDoc->createElement('PhotoOverlay'));

					$camera = $photoOverlay->appendChild($kmlDoc->createElement('Camera'));
					$camera->appendChild($kmlDoc->createElement('longitude'))
						->appendChild($kmlDoc->createTextNode((string)$pic['db_pic']->getLat()));
					$camera->appendChild($kmlDoc->createElement('latitude'))
						->appendChild($kmlDoc->createTextNode((string)$pic['db_pic']->getLon()));

					$coordinates = $pic['db_pic']->getLon() . ',' . $pic['db_pic']->getLat();
					$photoOverlay->appendChild($kmlDoc->createElement('Point'))
						->appendChild($kmlDoc->createElement('coordinates'))
						->appendChild($kmlDoc->createTextNode($coordinates));

					$photoOverlay->appendChild($kmlDoc->createElement('Icon'))
						->appendChild($kmlDoc->createElement('href'))
						->appendChild($kmlDoc->createTextNode('images/' . $picFileName));

					$dateTaken = $pic['db_pic']->getDateTaken();
					if ($dateTaken) {
						$formattedDate = (new \DateTime())->setTimestamp($dateTaken)->format('c');
						$photoOverlay->appendChild($kmlDoc->createElement('TimeStamp'))
							->appendChild($kmlDoc->createElement('when'))
							->appendChild($kmlDoc->createTextNode($formattedDate));
					}
				}
			}
		}
	}

	/**
	 * @param string $userId
	 * @param Directory $dir
	 * @return DOMDocument
	 * @throws \DOMException
	 * @throws \OCP\DB\Exception
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws LockedException
	 * @throws NoUserException
	 */
	private function getDirectoryKmlDocument(string $userId, Directory $dir): DOMDocument {
		$dirName = basename($dir->getPath());
		$kmlDoc = $this->toolsService->createDomKmlWithHeaders($dirName);

		$dbTracks = $this->trackMapper->getDirectoryTracksOfUser($userId, $dir->getId());
		$userFolder = $this->root->getUserFolder($userId);
		foreach ($dbTracks as $dbTrack) {
			$trackFile = $userFolder->get($dbTrack->getTrackpath());
			if ($trackFile instanceof File) {
				$trackFileName = $trackFile->getName();
				$gpxContent = $trackFile->getContent();
				$gpxContent = $this->toolsService->remove_utf8_bom($gpxContent);
				$gpxContent = $this->toolsService->sanitizeGpxContent($gpxContent);
				$this->addTrackFileToKml($trackFileName, $gpxContent, $kmlDoc);
			}
		}

		return $kmlDoc;
	}

	/**
	 * Add one <Placemark> to the KML document
	 *
	 * @param string $trackFileName
	 * @param string $trackFileContent
	 * @param DOMDocument $kmlDoc
	 * @return void
	 * @throws \DOMException
	 */
	private function addTrackFileToKml(string $trackFileName, string $trackFileContent, DOMDocument $kmlDoc): void {
		$documents = $kmlDoc->getElementsByTagName('Document');
		if ($documents->length > 0) {
			$document = $documents->item(0);

			$placemark = $document->appendChild($kmlDoc->createElement('Placemark'));
			$placemark->appendChild($kmlDoc->createElement('name'))->appendChild($kmlDoc->createTextNode($trackFileName));

			$multiTrack = $placemark->appendChild($kmlDoc->createElement('MultiTrack'));
			$multiTrack->appendChild($kmlDoc->createElement('altitudeMode'))->appendChild($kmlDoc->createTextNode('absolute'));
			$multiTrack->appendChild($kmlDoc->createElement('interpolate'))->appendChild($kmlDoc->createTextNode('1'));


			$gpx = new phpGPX();
			$gpxArray = $gpx->parse($trackFileContent);
			// one <Track> for each segment
			foreach ($gpxArray->tracks as $t) {
				foreach ($t->segments as $seg) {
					$this->addGpxSegmentToKmlMultiTrack($seg->points, $multiTrack, $kmlDoc);
				}
			}
			// one <Track> for each route
			foreach ($gpxArray->routes as $r) {
				$this->addGpxSegmentToKmlMultiTrack($r->points, $multiTrack, $kmlDoc);
			}
		}
	}

	/**
	 * @param array|Point[] $points
	 * @param DOMNode $multiTrack
	 * @param DOMDocument $kmlDoc
	 * @return void
	 * @throws \DOMException
	 */
	private function addGpxSegmentToKmlMultiTrack(array $points, DOMNode $multiTrack, DOMDocument $kmlDoc): void {
		$track = $multiTrack->appendChild($kmlDoc->createElement('Track'));
		$trackpointExtentions = [];
		$unsupportedExtentions = [];
		// first pass to add times, coordinates and elevation
		// and get extension list
		foreach ($points as $point) {
			if ($point->time === null) {
				$track->appendChild($kmlDoc->createElement('when'))->appendChild($kmlDoc->createTextNode(''));
			} else {
				$time = $point->time->format('c');
				// $time = $point->time->format('Y-m-d\TH:i:sP');
				$track->appendChild($kmlDoc->createElement('when'))->appendChild($kmlDoc->createTextNode($time));
			}
			if ($point->longitude !== null && $point->latitude !== null) {
				$coord = $point->longitude . ' ' . $point->latitude;
				if ($point->elevation !== null) {
					$coord .= ' ' . $point->elevation;
				}
				$track->appendChild($kmlDoc->createElement('coord'))->appendChild($kmlDoc->createTextNode($coord));
			} else {
				$track->appendChild($kmlDoc->createElement('coord'));
			}
			// extensions
			if ($point->extensions->trackPointExtension) {
				foreach ($point->extensions->trackPointExtension->toArray() as $key => $value) {
					$trackpointExtentions[] = $key;
				}
			}
			if ($point->extensions->unsupported) {
				foreach ($point->extensions->unsupported as $key => $value) {
					$unsupportedExtentions[] = $key;
				}
			}
		}
		$trackpointExtentions = array_unique($trackpointExtentions);
		$unsupportedExtentions = array_unique($unsupportedExtentions);
		if (count($trackpointExtentions) > 0 || count($unsupportedExtentions) > 0) {
			$schemaData = $track->appendChild($kmlDoc->createElement('ExtendedData'))
				->appendChild($kmlDoc->createElement('SchemaData'));
			$schemaData->appendChild($kmlDoc->createAttribute('schemaUrl'))->appendChild($kmlDoc->createTextNode('#schema'));
			foreach ($trackpointExtentions as $tpe) {
				$simpleArrayData = $schemaData->appendChild($kmlDoc->createElement('SimpleArrayData'));
				$simpleArrayData->appendChild($kmlDoc->createAttribute('name'))->appendChild($kmlDoc->createTextNode($tpe));
				foreach ($points as $point) {
					if ($point->extensions->trackPointExtension) {
						$value = $point->extensions->trackPointExtension->toArray()[$tpe] ?? '';
					} else {
						$value = '';
					}
					$simpleArrayData->appendChild($kmlDoc->createElement('value'))->appendChild($kmlDoc->createTextNode($value));
				}
			}
			foreach ($unsupportedExtentions as $tpe) {
				$simpleArrayData = $schemaData->appendChild($kmlDoc->createElement('SimpleArrayData'));
				$simpleArrayData->appendChild($kmlDoc->createAttribute('name'))->appendChild($kmlDoc->createTextNode($tpe));
				foreach ($points as $point) {
					if ($point->extensions->unsupported) {
						$value = $point->extensions->unsupported[$tpe] ?? '';
					} else {
						$value = '';
					}
					$simpleArrayData->appendChild($kmlDoc->createElement('value'))->appendChild($kmlDoc->createTextNode($value));
				}
			}
		}
	}
}
