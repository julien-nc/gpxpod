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

use adriangibbons\phpFITFileAnalysis;
use DateTime;
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
use OCA\GpxPod\AppInfo\Application;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use Throwable;
use ZipArchive;

require_once __DIR__ . '/../../vendor/autoload.php';

class ConversionService {

	public const FIT_EXTENSIONS = [
		'heart_rate',
		'distance',
		'speed',
		'temperature',
		'power',
		'calories',
		'vertical_speed',
	];
	public const fileExtToGpsbabelFormat = [
		'.kml' => 'kml',
		'.kmz' => 'kmz',
		'.gpx' => '',
		'.tcx' => 'gtrnctr',
		'.igc' => 'igc',
		'.jpg' => '',
		'.fit' => 'garmin_fit',
	];
	private IConfig $config;
	private ToolsService $toolsService;

	public function __construct(IConfig $config,
								ToolsService $toolsService) {
		$this->config = $config;
		$this->toolsService = $toolsService;
	}

	/**
	 * For points, get non-supported extensions out of the <gpxtpx:TrackPointExtension> element
	 * For tracks and routes, get sub extensions up on the top level
	 *
	 * @param string $gpxContent
	 * @return string
	 * @throws \DOMException
	 */
	public function sanitizeGpxExtensions(string $gpxContent): string {
		$dom = new DOMDocument('1.0', 'UTF-8');
		$dom->loadXML($gpxContent, LIBXML_NOBLANKS);

		$extensionsNodes = $dom->getElementsByTagName('extensions');
		foreach ($extensionsNodes as $extensionsNode) {
			if ($extensionsNode instanceof DOMNode) {
				$parentNodeName = $extensionsNode->parentNode->localName;
				if ($parentNodeName === 'trkpt') {
					foreach ($extensionsNode->childNodes as $ext) {
						if ($ext instanceof DOMNode && $ext->nodeName === 'gpxtpx:TrackPointExtension') {
							$nodesToPushUp = [];
							foreach ($ext->childNodes as $gpxtpxExt) {
								if ($gpxtpxExt instanceof DOMNode && $gpxtpxExt->prefix !== 'gpxtpx') {
									$nodesToPushUp[] = $gpxtpxExt;
								}
							}
							foreach ($nodesToPushUp as $node) {
								$removed = $ext->removeChild($node);
								// this keeps the prefix
								$extensionsNode->appendChild($removed);
//								$extensionsNode->appendChild(
//									$dom->createElement($removed->localName, $removed->nodeValue)
//								);
							}
						}
					}
				} elseif ($parentNodeName === 'trk' || $parentNodeName === 'rte') {
					$emptyExtensionToRemove = [];
					foreach ($extensionsNode->childNodes as $ext) {
						if ($ext instanceof DOMNode && count($ext->childNodes) > 0) {
							$nodesToPushUp = [];
							foreach ($ext->childNodes as $subExt) {
								if ($subExt instanceof DOMNode) {
									if ($subExt instanceof DOMElement) {
										$nodesToPushUp[] = $subExt;
									}
								}
							}
							foreach ($nodesToPushUp as $node) {
								$removed = $ext->removeChild($node);
								// this keeps the prefix
								$extensionsNode->appendChild($removed);
//								$extensionsNode->appendChild(
//									$dom->createElement($removed->localName, $removed->nodeValue)
//								);
							}
							// if we removed every sub extension in this extension, delete it
							if (count($nodesToPushUp) > 0 && count($ext->childNodes) === 0) {
								$emptyExtensionToRemove[] = $ext;
							}
						}
					}
					foreach ($emptyExtensionToRemove as $emptyExt) {
						$extensionsNode->removeChild($emptyExt);
					}
				}
			}
		}
		return $dom->saveXML();
	}

	/**
	 * Convert kml, igc, tcx and fit files to gpx and store them in the same directory
	 *
	 * @param Folder $userFolder
	 * @param string $subfolder
	 * @param string $userId
	 * @param array $filesByExtension
	 * @return int[]
	 * @throws NotFoundException
	 */
	public function convertFiles(Folder $userFolder, string $subfolder, string $userId, array $filesByExtension): array {
		$convertedFileCount = [
			'native' => 0,
			'gpsbabel' => 0,
		];

		if (   $userFolder->nodeExists($subfolder)
			&& $userFolder->get($subfolder)->getType() === FileInfo::TYPE_FOLDER) {

			$gpsbabel_path = $this->toolsService->getProgramPath('gpsbabel');
			$igctrack = $this->config->getUserValue($userId, Application::APP_ID, 'igctrack');
			$useGpsbabel = $this->config->getAppValue(Application::APP_ID, 'use_gpsbabel', '0') === '1';

			if ($useGpsbabel && $gpsbabel_path !== null) {
				foreach (self::fileExtToGpsbabelFormat as $ext => $gpsbabel_fmt) {
					if ($ext !== '.gpx' && $ext !== '.jpg') {
						$igcfilter1 = '';
						$igcfilter2 = '';
						if ($ext === '.igc') {
							if ($igctrack === 'pres') {
								$igcfilter1 = '-x';
								$igcfilter2 = 'track,name=PRESALTTRK';
							} elseif ($igctrack === 'gnss') {
								$igcfilter1 = '-x';
								$igcfilter2 = 'track,name=GNSSALTTRK';
							}
						}
						foreach ($filesByExtension[$ext] as $f) {
							$name = $f->getName();
							$gpxTargetName = str_replace($ext, '.gpx', $name);
							$gpxTargetName = str_replace(strtoupper($ext), '.gpx', $gpxTargetName);
							$gpxTargetFolder = $f->getParent();
							if (! $gpxTargetFolder->nodeExists($gpxTargetName)) {
								// we read content, then launch the command, then write content on stdin
								// then read gpsbabel stdout then write it in a NC file
								$content = $f->getContent();

								if ($igcfilter1 !== '') {
									$args = ['-i', $gpsbabel_fmt, '-f', '-',
										$igcfilter1, $igcfilter2, '-o',
										'gpx', '-F', '-'];
								} else {
									$args = ['-i', $gpsbabel_fmt, '-f', '-',
										'-o', 'gpx', '-F', '-'];
								}
								$cmdparams = '';
								foreach ($args as $arg) {
									$shella = escapeshellarg($arg);
									$cmdparams .= " $shella";
								}
								$descriptorspec = [
									0 => ['pipe', 'r'],
									1 => ['pipe', 'w'],
									2 => ['pipe', 'w']
								];
								$process = proc_open(
									$gpsbabel_path.' '.$cmdparams,
									$descriptorspec,
									$pipes
								);
								// write to stdin
								fwrite($pipes[0], $content);
								fclose($pipes[0]);
								// read from stdout
								$gpx_clear_content = stream_get_contents($pipes[1]);
								fclose($pipes[1]);
								// read from stderr
								$stderr = stream_get_contents($pipes[2]);
								fclose($pipes[2]);

								$return_value = proc_close($process);

								// write result in NC files
								$gpx_file = $gpxTargetFolder->newFile($gpxTargetName);
								$gpx_file->putContent($gpx_clear_content);
								$convertedFileCount['gpsbabel']++;
							}
						}
					}
				}
			} else {
				// Fallback for igc without GpsBabel
				foreach ($filesByExtension['.igc'] as $f) {
					$name = $f->getName();
					$gpxTargetName = preg_replace('/\.igc$/i', '.gpx', $name);
					$gpxTargetFolder = $f->getParent();
					if (! $gpxTargetFolder->nodeExists($gpxTargetName)) {
						try {
							$fdesc = $f->fopen('r');
							$gpx_clear_content = $this->igcToGpx($fdesc, $igctrack);
							fclose($fdesc);
							$gpx_file = $gpxTargetFolder->newFile($gpxTargetName);
							$gpx_file->putContent($gpx_clear_content);
							$convertedFileCount['native']++;
						} catch (Exception | Throwable $e) {
						}
					}
				}
				// Fallback KML conversion without GpsBabel
				foreach ($filesByExtension['.kml'] as $f) {
					$name = $f->getName();
					$gpxTargetName = preg_replace('/\.kml$/i', '.gpx', $name);
					$gpxTargetFolder = $f->getParent();
					if (! $gpxTargetFolder->nodeExists($gpxTargetName)) {
						try {
							$content = $f->getContent();
							$gpx_clear_content = $this->kmlToGpx($content);
							$gpx_file = $gpxTargetFolder->newFile($gpxTargetName);
							$gpx_file->putContent($gpx_clear_content);
							$convertedFileCount['native']++;
						} catch (Exception | Throwable $e) {
						}
					}
				}
				foreach ($filesByExtension['.kmz'] as $f) {
					$name = $f->getName();
					$gpxTargetName = preg_replace('/\.kmz$/i', '.gpx', $name);
					$gpxTargetFolder = $f->getParent();
					if (! $gpxTargetFolder->nodeExists($gpxTargetName)) {
						try {
							$content = $f->getContent();
							$gpx_clear_content = $this->kmzToGpx($content, $f->getName(), $gpxTargetFolder);
							$gpx_file = $gpxTargetFolder->newFile($gpxTargetName);
							$gpx_file->putContent($gpx_clear_content);
							$convertedFileCount['native']++;
						} catch (Exception | Throwable $e) {
						}
					}
				}
				// Fallback TCX conversion without GpsBabel
				foreach ($filesByExtension['.tcx'] as $f) {
					$name = $f->getName();
					$gpxTargetName = preg_replace('/\.tcx$/i', '.gpx', $name);
					$gpxTargetFolder = $f->getParent();
					if (! $gpxTargetFolder->nodeExists($gpxTargetName)) {
						try {
							$content = $f->getContent();
							$gpx_clear_content = $this->tcxToGpx($content);
							$gpx_file = $gpxTargetFolder->newFile($gpxTargetName);
							$gpx_file->putContent($gpx_clear_content);
							$convertedFileCount['native']++;
						} catch (Exception | Throwable $e) {
						}
					}
				}
				foreach ($filesByExtension['.fit'] as $f) {
					$name = $f->getName();
					$gpxTargetName = preg_replace('/\.fit$/i', '.gpx', $name);
					$gpxTargetFolder = $f->getParent();
					if (!$gpxTargetFolder->nodeExists($gpxTargetName)) {
						try {
							$content = $f->getContent();
							$gpx_clear_content = $this->fitToGpx($content);
							if ($gpx_clear_content !== null) {
								$gpx_file = $gpxTargetFolder->newFile($gpxTargetName);
								$gpx_file->putContent($gpx_clear_content);
								$convertedFileCount['native']++;
							}
						} catch (Exception | Throwable $e) {
						}
					}
				}
			}
		}
		return $convertedFileCount;
	}

	/**
	 * @param string $fitContent
	 * @return string|null
	 * @throws \DOMException
	 */
	public function fitToGpx(string $fitContent): ?string {
		$fitFile = new phpFITFileAnalysis($fitContent, ['input_is_data' => true]);

		$domGpx = $this->createDomGpxWithHeaders();
		$rootNode = $domGpx->getElementsByTagName('gpx')->item(0);
		$trkNode = $rootNode->appendChild($domGpx->createElement('trk'));
		$trksegNode = $trkNode->appendChild($domGpx->createElement('trkseg'));

		$pointCount = 0;

		foreach ($fitFile->data_mesgs['record']['timestamp'] as $timestamp) {
			if (isset($fitFile->data_mesgs['record']['position_lat'][$timestamp], $fitFile->data_mesgs['record']['position_long'][$timestamp])
				&& $fitFile->data_mesgs['record']['position_lat'][$timestamp]
				&& $fitFile->data_mesgs['record']['position_long'][$timestamp]
			) {
				$pointCount++;
				$lat = $fitFile->data_mesgs['record']['position_lat'][$timestamp];
				$lon = $fitFile->data_mesgs['record']['position_long'][$timestamp];
				$time = date('Y-m-d\TH:i:s.000\Z', $timestamp);

				$pointNode = $trksegNode->appendChild($domGpx->createElement('trkpt'));
				$pointNode
					->appendChild($domGpx->createAttribute('lat'))
					->appendChild($domGpx->createTextNode($lat));
				$pointNode
					->appendChild($domGpx->createAttribute('lon'))
					->appendChild($domGpx->createTextNode($lon));
				$pointNode
					->appendChild($domGpx->createElement('time'))
					->appendChild($domGpx->createTextNode($time));

				if ($fitFile->data_mesgs['record']['altitude'][$timestamp]) {
					$pointNode
						->appendChild($domGpx->createElement('ele'))
						->appendChild($domGpx->createTextNode($fitFile->data_mesgs['record']['altitude'][$timestamp]));
				}
				$extensions = null;
				foreach (self::FIT_EXTENSIONS as $ext) {
					if (isset($fitFile->data_mesgs['record'][$ext][$timestamp]) && $fitFile->data_mesgs['record'][$ext][$timestamp]) {
						if ($extensions === null) {
							$extensions = $pointNode->appendChild($domGpx->createElement('extensions'));
						}
						$extensions
							->appendChild($domGpx->createElement($ext))
							->appendChild($domGpx->createTextNode($fitFile->data_mesgs['record'][$ext][$timestamp]));
					}
				}
			}
		}

		if ($pointCount === 0) {
			return null;
		}
		return $domGpx->saveXML();
	}

	private function utcdate() {
		return gmdate('Y-m-d\Th:i:s\Z');
	}

	// get decimal coordinate from exif data
	public function getDecimalCoords($exifCoord, $hemi) {
		$degrees = count($exifCoord) > 0 ? $this->exifCoordToNumber($exifCoord[0]) : 0;
		$minutes = count($exifCoord) > 1 ? $this->exifCoordToNumber($exifCoord[1]) : 0;
		$seconds = count($exifCoord) > 2 ? $this->exifCoordToNumber($exifCoord[2]) : 0;

		$flip = ($hemi === 'W' or $hemi === 'S') ? -1 : 1;

		return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
	}

	// parse the coordinate string to calculate the float value
	private function exifCoordToNumber($coordPart) {
		$parts = explode('/', $coordPart);

		if (count($parts) <= 0)
			return 0;

		if (count($parts) === 1)
			return $parts[0];

		return floatval($parts[0]) / floatval($parts[1]);
	}

	private function createDomGpxWithHeaders() {
		$domGpx = new DOMDocument('1.0', 'UTF-8');
		$domGpx->formatOutput = true;

		//root node
		$gpx = $domGpx->createElement('gpx');
		$gpx = $domGpx->appendChild($gpx);

		$gpx_version = $domGpx->createAttribute('version');
		$gpx->appendChild($gpx_version);
		$gpx_version_text = $domGpx->createTextNode('1.0');
		$gpx_version->appendChild($gpx_version_text);

		$gpx_creator = $domGpx->createAttribute('creator');
		$gpx->appendChild($gpx_creator);
		$gpx_creator_text = $domGpx->createTextNode('GpxPod conversion tool');
		$gpx_creator->appendChild($gpx_creator_text);

		$gpx_xmlns_xsi = $domGpx->createAttribute('xmlns:xsi');
		$gpx->appendChild($gpx_xmlns_xsi);
		$gpx_xmlns_xsi_text = $domGpx->createTextNode('http://www.w3.org/2001/XMLSchema-instance');
		$gpx_xmlns_xsi->appendChild($gpx_xmlns_xsi_text);

		$gpx_xmlns = $domGpx->createAttribute('xmlns');
		$gpx->appendChild($gpx_xmlns);
		$gpx_xmlns_text = $domGpx->createTextNode('http://www.topografix.com/GPX/1/0');
		$gpx_xmlns->appendChild($gpx_xmlns_text);

		$gpx_xsi_schemaLocation = $domGpx->createAttribute('xsi:schemaLocation');
		$gpx->appendChild($gpx_xsi_schemaLocation);
		$gpx_xsi_schemaLocation_text = $domGpx->createTextNode('http://www.topografix.com/GPX/1/0 http://www.topografix.com/GPX/1/0/gpx.xsd');
		$gpx_xsi_schemaLocation->appendChild($gpx_xsi_schemaLocation_text);

		$gpx_time = $domGpx->createElement('time');
		$gpx_time = $gpx->appendChild($gpx_time);
		$gpx_time_text = $domGpx->createTextNode($this->utcdate());
		$gpx_time->appendChild($gpx_time_text);

		return $domGpx;
	}

	public function jpgToGpx($jpgFilePath, $fileName) {
		$result = '';
		$exif = \exif_read_data($jpgFilePath, 0, true);
		if (    isset($exif['GPS'])
			and isset($exif['GPS']['GPSLongitude'])
			and isset($exif['GPS']['GPSLatitude'])
			and isset($exif['GPS']['GPSLatitudeRef'])
			and isset($exif['GPS']['GPSLongitudeRef'])
		) {
			$lon = $this->getDecimalCoords($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
			$lat = $this->getDecimalCoords($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);

			$domGpx = $this->createDomGpxWithHeaders();
			$gpx = $domGpx->getElementsByTagName('gpx')->item(0);

			$gpx_wpt = $domGpx->createElement('wpt');
			$gpx_wpt = $gpx->appendChild($gpx_wpt);

			$gpx_wpt_lat = $domGpx->createAttribute('lat');
			$gpx_wpt->appendChild($gpx_wpt_lat);
			$gpx_wpt_lat_text = $domGpx->createTextNode($lat);
			$gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

			$gpx_wpt_lon = $domGpx->createAttribute('lon');
			$gpx_wpt->appendChild($gpx_wpt_lon);
			$gpx_wpt_lon_text = $domGpx->createTextNode($lon);
			$gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

			$gpx_name = $domGpx->createElement('name');
			$gpx_name = $gpx_wpt->appendChild($gpx_name);
			$gpx_name_text = $domGpx->createTextNode($fileName);
			$gpx_name->appendChild($gpx_name_text);

			$gpx_symbol = $domGpx->createElement('sym');
			$gpx_symbol = $gpx_wpt->appendChild($gpx_symbol);
			$gpx_symbol_text = $domGpx->createTextNode('Flag, Blue');
			$gpx_symbol->appendChild($gpx_symbol_text);

			$result = $domGpx->saveXML();
		}
		return $result;
	}

	public function igcToGpx($fh, $trackOptions) {
		$domGpx = $this->createDomGpxWithHeaders();
		$gpx = $domGpx->getElementsByTagName('gpx')->item(0);

		$hasBaro = false;
		$date = new DateTime();
		$date->setTimestamp(0);
		//Parse header and detect baro altitude
		while ($line = fgets($fh)) {
			if (substr($line,0,5)==='HFDTE') {
				$date->setTimestamp(strtotime(
					substr($line,5,2).'.'
					.substr($line,7,2).'.'
					.(intval(substr($line,9,2))<70?'20':'19').substr($line,9,2)
				));
			} elseif (substr($line,0,10)==='HFPLTPILOT') {
				$author = trim(explode(':', $line,2)[1]);
				$gpx_author = $domGpx->createElement('author');
				$gpx->insertBefore($gpx_author,$domGpx->getElementsByTagName('time')->item(0));
				$gpx_author_text = $domGpx->createTextNode($author);
				$gpx_author->appendChild($gpx_author_text);
			} elseif ($line[0] === 'B') {
				$hasBaro = intval(substr($line, 25,5)) !== 0;
				if ($hasBaro) {
					break;
				}
			}
		}
		rewind($fh);
		$includeGnss = !$hasBaro || $trackOptions!=='pres';
		$includeBaro = $hasBaro && $trackOptions!=='gnss';

		if ($includeGnss) {
			$gpx_trk = $domGpx->createElement('trk');
			$gpx_trk_name = $domGpx->createElement('name');
			$gpx_trk_name->nodeValue = 'GNSSALTTRK';
			$gpx_trk->appendChild($gpx_trk_name);
			$gpx_trkseg = $domGpx->createElement('trkseg');
			$gpx_trk->appendChild($gpx_trkseg);
			$gpx->appendChild($gpx_trk);
		}

		if ($includeBaro) {
			$gpx_trk_baro = $domGpx->createElement('trk');
			$gpx_trk_baro_name = $domGpx->createElement('name');
			$gpx_trk_baro_name->nodeValue = 'PRESALTTRK';
			$gpx_trk_baro->appendChild($gpx_trk_baro_name);
			$gpx->appendChild($gpx_trk_baro);
			$gpx_trkseg_baro = $domGpx->createElement('trkseg');
			$gpx_trk_baro->appendChild($gpx_trkseg_baro);
		}

		//Parse tracklog
		while ($line =  fgets($fh)) {
			$type = $line[0];
			if ($type==='B') {
				$minutesLat = round((floatval('0.'.substr($line, 9,5))/60)*100,5);
				$lat = floatval(intval(substr($line, 7,2))+$minutesLat)*($line[14]==='N'?1:-1);
				$minutesLon = round((floatval('0.'.substr($line, 18,5))/60)*100,5);
				$lon = floatval(intval(substr($line, 15,3))+$minutesLon)*($line[23]==='E'?1:-1);

				$gpx_trkpt = $domGpx->createElement('trkpt');

				if ($includeGnss) {
					$gpx_trkseg->appendChild($gpx_trkpt);
				}

				$gpx_wpt_lat = $domGpx->createAttribute('lat');
				$gpx_trkpt->appendChild($gpx_wpt_lat);
				$gpx_wpt_lat_text = $domGpx->createTextNode($lat);
				$gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

				$gpx_wpt_lon = $domGpx->createAttribute('lon');
				$gpx_trkpt->appendChild($gpx_wpt_lon);
				$gpx_wpt_lon_text = $domGpx->createTextNode($lon);
				$gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

				$gpx_ele = $domGpx->createElement('ele');
				$gpx_trkpt->appendChild($gpx_ele);
				$gpx_ele_text = $domGpx->createTextNode(intval(substr($line, 30,5)));
				$gpx_ele->appendChild($gpx_ele_text);

				$gpx_time = $domGpx->createElement('time');
				$gpx_trkpt->appendChild($gpx_time);
				$gpx_time_text = $domGpx->createTextNode(
					$date->format('Y-m-d').
					'T'.substr($line,1,2).':'.substr($line,3,2).':'.substr($line,5,2)
				);
				$gpx_time->appendChild($gpx_time_text);

				if ($includeBaro) {
					$gpx_trkpt_baro = $gpx_trkpt->cloneNode(true);
					$ele = $gpx_trkpt_baro->getElementsByTagName('ele')->item(0);
					$ele->nodeValue = intval(substr($line, 25,5));
					$gpx_trkseg_baro->appendChild($gpx_trkpt_baro);
				}
			}
		}
		return $domGpx->saveXML();
	}

	/**
	 * find the first kml file at the top level of the kmz zip archive
	 * and converts it
	 *
	 * @param string $kmzContent
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
        $longitudeRef= ($longitudeDegreeDecimal >= 0) ? 'E' : 'W';
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

		$domGpx = $this->createDomGpxWithHeaders();
		$gpx = $domGpx->getElementsByTagName('gpx')->item(0);

		// placemarks
		$names = [];
		foreach ($domKml->getElementsByTagName('Placemark') as $placemark) {
			//name
			foreach ($placemark->getElementsByTagName('name') as $name) {
				$name  = $name->nodeValue;
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
				$description  = $description->nodeValue;
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
							->appendChild($domGpx->createTextNode($this->utcdate()));
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
								$time = $this->utcdate();
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
							$time = $this->utcdate();
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

		$gpxPoint
			->appendChild($dom->createAttribute('lat'))
			->appendChild($dom->createTextNode($lat));
		$gpxPoint
			->appendChild($dom->createAttribute('lon'))
			->appendChild($dom->createTextNode($lon));
		$gpxPoint
			->appendChild($dom->createElement('time'))
			->appendChild($dom->createTextNode($time));

		if ($ele !== null) {
			$gpxPoint
				->appendChild($dom->createElement('ele'))
				->appendChild($dom->createTextNode($ele));
		}
		return $gpxPoint;
	}

	public function unicsvToGpx($csvFilePath) {
		$domGpx = $this->createDomGpxWithHeaders();
		$gpx = $domGpx->getElementsByTagName('gpx')->item(0);

		$csv = array_map('str_getcsv', file($csvFilePath, FILE_SKIP_EMPTY_LINES));
		$keys = array_shift($csv);

		foreach ($csv as $i=>$row) {
			$csv[$i] = array_combine($keys, $row);
		}

		foreach ($csv as $line) {
			$lat = $line['Latitude'];
			$lon = $line['Longitude'];

			$gpx_wpt = $domGpx->createElement('wpt');
			$gpx_wpt = $gpx->appendChild($gpx_wpt);

			$gpx_wpt_lat = $domGpx->createAttribute('lat');
			$gpx_wpt->appendChild($gpx_wpt_lat);
			$gpx_wpt_lat_text = $domGpx->createTextNode($lat);
			$gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

			$gpx_wpt_lon = $domGpx->createAttribute('lon');
			$gpx_wpt->appendChild($gpx_wpt_lon);
			$gpx_wpt_lon_text = $domGpx->createTextNode($lon);
			$gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

			if (array_key_exists('Symbol', $line)) {
				$gpx_symbol = $domGpx->createElement('sym');
				$gpx_symbol = $gpx_wpt->appendChild($gpx_symbol);
				$gpx_symbol_text = $domGpx->createTextNode($line['Symbol']);
				$gpx_symbol->appendChild($gpx_symbol_text);
			}
			if (array_key_exists('Name', $line)) {
				$gpx_name = $domGpx->createElement('name');
				$gpx_name = $gpx_wpt->appendChild($gpx_name);
				$gpx_name_text = $domGpx->createTextNode($line['Name']);
				$gpx_name->appendChild($gpx_name_text);
			}

		}
		return $domGpx->saveXML();
	}

	/**
	 * @param string $tcxContent
	 * @return string
	 * @throws \DOMException
	 */
	public function tcxToGpx(string $tcxContent): string {
		$domTcx = new DOMDocument();
		$domTcx->loadXML($tcxContent);

		$domGpx = $this->createDomGpxWithHeaders();
		$gpx = $domGpx->getElementsByTagName('gpx')->item(0);

		foreach ($domTcx->getElementsByTagName('Course') as $course) {
			$name = '';
			foreach ($course->getElementsByTagName('Name') as $name) {
				$name  = $name->nodeValue;
			}
			//add the new track
			$gpx_trk = $domGpx->createElement('trk');
			$gpx_trk = $gpx->appendChild($gpx_trk);

			$gpx_name = $domGpx->createElement('name');
			$gpx_name = $gpx_trk->appendChild($gpx_name);
			$gpx_name_text = $domGpx->createTextNode($name);
			$gpx_name->appendChild($gpx_name_text);

			foreach ($course->getElementsByTagName('Track') as $track) {

				$gpx_trkseg = $domGpx->createElement('trkseg');
				$gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

				foreach ($track->getElementsByTagName('Trackpoint') as $trackpoint) {

					$gpx_trkpt = $domGpx->createElement('trkpt');
					$gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

					foreach ($trackpoint->getElementsByTagName('Time') as $time) {
						$gpx_time = $domGpx->createElement('time');
						$gpx_time = $gpx_trkpt->appendChild($gpx_time);
						$gpx_time_text = $domGpx->createTextNode($time->nodeValue);
						$gpx_time->appendChild($gpx_time_text);
					}
					foreach ($trackpoint->getElementsByTagName('Position') as $position) {
						foreach ($trackpoint->getElementsByTagName('LatitudeDegrees') as $lat) {
							$gpx_trkpt_lat = $domGpx->createAttribute('lat');
							$gpx_trkpt->appendChild($gpx_trkpt_lat);
							$gpx_trkpt_lat_text = $domGpx->createTextNode($lat->nodeValue);
							$gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);
						}
						foreach ($trackpoint->getElementsByTagName('LongitudeDegrees') as $lon) {
							$gpx_trkpt_lon = $domGpx->createAttribute('lon');
							$gpx_trkpt->appendChild($gpx_trkpt_lon);
							$gpx_trkpt_lon_text = $domGpx->createTextNode($lon->nodeValue);
							$gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);
						}
					}
					foreach ($trackpoint->getElementsByTagName('AltitudeMeters') as $ele) {
						$gpx_ele = $domGpx->createElement('ele');
						$gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
						$gpx_ele_text = $domGpx->createTextNode($ele->nodeValue);
						$gpx_ele->appendChild($gpx_ele_text);
					}
				}
			}
		}

		foreach ($domTcx->getElementsByTagName('Activity') as $activity) {
			$name = '';

			//add the new track
			$gpx_trk = $domGpx->createElement('trk');
			$gpx_trk = $gpx->appendChild($gpx_trk);

			$gpx_name = $domGpx->createElement('name');
			$gpx_name = $gpx_trk->appendChild($gpx_name);
			$gpx_name_text = $domGpx->createTextNode($name);
			$gpx_name->appendChild($gpx_name_text);

			foreach ($activity->getElementsByTagName('Lap') as $lap) {

				foreach ($lap->getElementsByTagName('Track') as $track) {

					$gpx_trkseg = $domGpx->createElement('trkseg');
					$gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

					foreach ($track->getElementsByTagName('Trackpoint') as $trackpoint) {

						$gpx_trkpt = $domGpx->createElement('trkpt');
						$gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

						foreach ($trackpoint->getElementsByTagName('Time') as $time) {
							$gpx_time = $domGpx->createElement('time');
							$gpx_time = $gpx_trkpt->appendChild($gpx_time);
							$gpx_time_text = $domGpx->createTextNode($time->nodeValue);
							$gpx_time->appendChild($gpx_time_text);
						}
						foreach ($trackpoint->getElementsByTagName('Position') as $position) {
							foreach ($trackpoint->getElementsByTagName('LatitudeDegrees') as $lat) {
								$gpx_trkpt_lat = $domGpx->createAttribute('lat');
								$gpx_trkpt->appendChild($gpx_trkpt_lat);
								$gpx_trkpt_lat_text = $domGpx->createTextNode($lat->nodeValue);
								$gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);
							}
							foreach ($trackpoint->getElementsByTagName('LongitudeDegrees') as $lon) {
								$gpx_trkpt_lon = $domGpx->createAttribute('lon');
								$gpx_trkpt->appendChild($gpx_trkpt_lon);
								$gpx_trkpt_lon_text = $domGpx->createTextNode($lon->nodeValue);
								$gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);
							}
						}
						foreach ($trackpoint->getElementsByTagName('AltitudeMeters') as $ele) {
							$gpx_ele = $domGpx->createElement('ele');
							$gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
							$gpx_ele_text = $domGpx->createTextNode($ele->nodeValue);
							$gpx_ele->appendChild($gpx_ele_text);
						}
					}
				}
			}
		}

		return $domGpx->saveXML();
	}
}
