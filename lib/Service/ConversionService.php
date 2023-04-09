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
use DOMXPath;
use Exception;
use OCA\GpxPod\AppInfo\Application;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use SimpleXMLElement;

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
								// $extensionNode->appendChild($removed);
								$extensionsNode->appendChild(
									$dom->createElement($removed->localName, $removed->nodeValue)
								);
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
								// $extensionNode->appendChild($removed);
								$extensionsNode->appendChild(
									$dom->createElement($removed->localName, $removed->nodeValue)
								);
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
							$gpx_targetname = str_replace($ext, '.gpx', $name);
							$gpx_targetname = str_replace(strtoupper($ext), '.gpx', $gpx_targetname);
							$gpx_targetfolder = $f->getParent();
							if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
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
								$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
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
					$gpx_targetname = str_replace(['.igc', '.IGC'], '.gpx', $name);
					$gpx_targetfolder = $f->getParent();
					if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
						$fdesc = $f->fopen('r');
						$gpx_clear_content = $this->igcToGpx($fdesc, $igctrack);
						fclose($fdesc);
						$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
						$gpx_file->putContent($gpx_clear_content);
						$convertedFileCount['native']++;
					}
				}
				// Fallback KML conversion without GpsBabel
				foreach ($filesByExtension['.kml'] as $f) {
					$name = $f->getName();
					$gpx_targetname = str_replace(['.kml', '.KML'], '.gpx', $name);
					$gpx_targetfolder = $f->getParent();
					if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
						$content = $f->getContent();
						$gpx_clear_content = $this->kmlToGpx($content);
						$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
						$gpx_file->putContent($gpx_clear_content);
						$convertedFileCount['native']++;
					}
				}
				// Fallback TCX conversion without GpsBabel
				foreach ($filesByExtension['.tcx'] as $f) {
					$name = $f->getName();
					$gpx_targetname = str_replace(['.tcx', '.TCX'], '.gpx', $name);
					$gpx_targetfolder = $f->getParent();
					if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
						$content = $f->getContent();
						$gpx_clear_content = $this->tcxToGpx($content);
						$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
						$gpx_file->putContent($gpx_clear_content);
						$convertedFileCount['native']++;
					}
				}
				foreach ($filesByExtension['.fit'] as $f) {
					$name = $f->getName();
					$gpx_targetname = str_replace(['.fit', '.FIT'], '.gpx', $name);
					$gpx_targetfolder = $f->getParent();
					if (!$gpx_targetfolder->nodeExists($gpx_targetname)) {
						$content = $f->getContent();
						$gpx_clear_content = $this->fitToGpx($content);
						if ($gpx_clear_content !== null) {
							$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
							$gpx_file->putContent($gpx_clear_content);
							$convertedFileCount['native']++;
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

		$dom_gpx = $this->createDomGpxWithHeaders();
		$rootNode = $dom_gpx->getElementsByTagName('gpx')->item(0);
		$trkNode = $rootNode->appendChild($dom_gpx->createElement('trk'));
		$trksegNode = $trkNode->appendChild($dom_gpx->createElement('trkseg'));

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

				$pointNode = $trksegNode->appendChild($dom_gpx->createElement('trkpt'));
				$pointNode
					->appendChild($dom_gpx->createAttribute('lat'))
					->appendChild($dom_gpx->createTextNode($lat));
				$pointNode
					->appendChild($dom_gpx->createAttribute('lon'))
					->appendChild($dom_gpx->createTextNode($lon));
				$pointNode
					->appendChild($dom_gpx->createElement('time'))
					->appendChild($dom_gpx->createTextNode($time));

				if ($fitFile->data_mesgs['record']['altitude'][$timestamp]) {
					$pointNode
						->appendChild($dom_gpx->createElement('ele'))
						->appendChild($dom_gpx->createTextNode($fitFile->data_mesgs['record']['altitude'][$timestamp]));
				}
				$extensions = null;
				foreach (self::FIT_EXTENSIONS as $ext) {
					if (isset($fitFile->data_mesgs['record'][$ext][$timestamp]) && $fitFile->data_mesgs['record'][$ext][$timestamp]) {
						if ($extensions === null) {
							$extensions = $pointNode->appendChild($dom_gpx->createElement('extensions'));
						}
						$extensions
							->appendChild($dom_gpx->createElement($ext))
							->appendChild($dom_gpx->createTextNode($fitFile->data_mesgs['record'][$ext][$timestamp]));
					}
				}
			}
		}

		if ($pointCount === 0) {
			return null;
		}
		return $dom_gpx->saveXML();
	}

	private function utcdate() {
		return gmdate("Y-m-d\Th:i:s\Z");
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
		$dom_gpx = new DOMDocument('1.0', 'UTF-8');
		$dom_gpx->formatOutput = true;

		//root node
		$gpx = $dom_gpx->createElement('gpx');
		$gpx = $dom_gpx->appendChild($gpx);

		$gpx_version = $dom_gpx->createAttribute('version');
		$gpx->appendChild($gpx_version);
		$gpx_version_text = $dom_gpx->createTextNode('1.0');
		$gpx_version->appendChild($gpx_version_text);

		$gpx_creator = $dom_gpx->createAttribute('creator');
		$gpx->appendChild($gpx_creator);
		$gpx_creator_text = $dom_gpx->createTextNode('GpxPod conversion tool');
		$gpx_creator->appendChild($gpx_creator_text);

		$gpx_xmlns_xsi = $dom_gpx->createAttribute('xmlns:xsi');
		$gpx->appendChild($gpx_xmlns_xsi);
		$gpx_xmlns_xsi_text = $dom_gpx->createTextNode('http://www.w3.org/2001/XMLSchema-instance');
		$gpx_xmlns_xsi->appendChild($gpx_xmlns_xsi_text);

		$gpx_xmlns = $dom_gpx->createAttribute('xmlns');
		$gpx->appendChild($gpx_xmlns);
		$gpx_xmlns_text = $dom_gpx->createTextNode('http://www.topografix.com/GPX/1/0');
		$gpx_xmlns->appendChild($gpx_xmlns_text);

		$gpx_xsi_schemaLocation = $dom_gpx->createAttribute('xsi:schemaLocation');
		$gpx->appendChild($gpx_xsi_schemaLocation);
		$gpx_xsi_schemaLocation_text = $dom_gpx->createTextNode('http://www.topografix.com/GPX/1/0 http://www.topografix.com/GPX/1/0/gpx.xsd');
		$gpx_xsi_schemaLocation->appendChild($gpx_xsi_schemaLocation_text);

		$gpx_time = $dom_gpx->createElement('time');
		$gpx_time = $gpx->appendChild($gpx_time);
		$gpx_time_text = $dom_gpx->createTextNode($this->utcdate());
		$gpx_time->appendChild($gpx_time_text);

		return $dom_gpx;
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

			$dom_gpx = $this->createDomGpxWithHeaders();
			$gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

			$gpx_wpt = $dom_gpx->createElement('wpt');
			$gpx_wpt = $gpx->appendChild($gpx_wpt);

			$gpx_wpt_lat = $dom_gpx->createAttribute('lat');
			$gpx_wpt->appendChild($gpx_wpt_lat);
			$gpx_wpt_lat_text = $dom_gpx->createTextNode($lat);
			$gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

			$gpx_wpt_lon = $dom_gpx->createAttribute('lon');
			$gpx_wpt->appendChild($gpx_wpt_lon);
			$gpx_wpt_lon_text = $dom_gpx->createTextNode($lon);
			$gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

			$gpx_name = $dom_gpx->createElement('name');
			$gpx_name = $gpx_wpt->appendChild($gpx_name);
			$gpx_name_text = $dom_gpx->createTextNode($fileName);
			$gpx_name->appendChild($gpx_name_text);

			$gpx_symbol = $dom_gpx->createElement('sym');
			$gpx_symbol = $gpx_wpt->appendChild($gpx_symbol);
			$gpx_symbol_text = $dom_gpx->createTextNode('Flag, Blue');
			$gpx_symbol->appendChild($gpx_symbol_text);

			$result = $dom_gpx->saveXML();
		}
		return $result;
	}

	public function igcToGpx($fh, $trackOptions) {
		$dom_gpx = $this->createDomGpxWithHeaders();
		$gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

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
				$gpx_author = $dom_gpx->createElement('author');
				$gpx->insertBefore($gpx_author,$dom_gpx->getElementsByTagName('time')->item(0));
				$gpx_author_text = $dom_gpx->createTextNode($author);
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
			$gpx_trk = $dom_gpx->createElement('trk');
			$gpx_trk_name = $dom_gpx->createElement('name');
			$gpx_trk_name->nodeValue = 'GNSSALTTRK';
			$gpx_trk->appendChild($gpx_trk_name);
			$gpx_trkseg = $dom_gpx->createElement('trkseg');
			$gpx_trk->appendChild($gpx_trkseg);
			$gpx->appendChild($gpx_trk);
		}

		if ($includeBaro) {
			$gpx_trk_baro = $dom_gpx->createElement('trk');
			$gpx_trk_baro_name = $dom_gpx->createElement('name');
			$gpx_trk_baro_name->nodeValue = 'PRESALTTRK';
			$gpx_trk_baro->appendChild($gpx_trk_baro_name);
			$gpx->appendChild($gpx_trk_baro);
			$gpx_trkseg_baro = $dom_gpx->createElement('trkseg');
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

				$gpx_trkpt = $dom_gpx->createElement('trkpt');

				if ($includeGnss) {
					$gpx_trkseg->appendChild($gpx_trkpt);
				}

				$gpx_wpt_lat = $dom_gpx->createAttribute('lat');
				$gpx_trkpt->appendChild($gpx_wpt_lat);
				$gpx_wpt_lat_text = $dom_gpx->createTextNode($lat);
				$gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

				$gpx_wpt_lon = $dom_gpx->createAttribute('lon');
				$gpx_trkpt->appendChild($gpx_wpt_lon);
				$gpx_wpt_lon_text = $dom_gpx->createTextNode($lon);
				$gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

				$gpx_ele = $dom_gpx->createElement('ele');
				$gpx_trkpt->appendChild($gpx_ele);
				$gpx_ele_text = $dom_gpx->createTextNode(intval(substr($line, 30,5)));
				$gpx_ele->appendChild($gpx_ele_text);

				$gpx_time = $dom_gpx->createElement('time');
				$gpx_trkpt->appendChild($gpx_time);
				$gpx_time_text = $dom_gpx->createTextNode(
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
		return $dom_gpx->saveXML();
	}

	public function kmlToGpx($kmlcontent) {
		$dom_kml = new DOMDocument();
		$dom_kml->loadXML($kmlcontent);

		$dom_gpx = $this->createDomGpxWithHeaders();
		$gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

		// placemarks
		$names = array();
		foreach ($dom_kml->getElementsByTagName('Placemark') as $placemark) {
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
					$coordinate = str_replace(" ", "", $coordinate);//trim white space
					$latlng = explode(",", $coordinate);

					if (($lat = $latlng[1]) && ($lng = $latlng[0])) {
						$gpx_wpt = $dom_gpx->createElement('wpt');
						$gpx_wpt = $gpx->appendChild($gpx_wpt);

						$gpx_wpt_lat = $dom_gpx->createAttribute('lat');
						$gpx_wpt->appendChild($gpx_wpt_lat);
						$gpx_wpt_lat_text = $dom_gpx->createTextNode($lat);
						$gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

						$gpx_wpt_lon = $dom_gpx->createAttribute('lon');
						$gpx_wpt->appendChild($gpx_wpt_lon);
						$gpx_wpt_lon_text = $dom_gpx->createTextNode($lng);
						$gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

						$gpx_time = $dom_gpx->createElement('time');
						$gpx_time = $gpx_wpt->appendChild($gpx_time);
						$gpx_time_text = $dom_gpx->createTextNode($this->utcdate());
						$gpx_time->appendChild($gpx_time_text);

						$gpx_name = $dom_gpx->createElement('name');
						$gpx_name = $gpx_wpt->appendChild($gpx_name);
						$gpx_name_text = $dom_gpx->createTextNode($name);
						$gpx_name->appendChild($gpx_name_text);

						$gpx_desc = $dom_gpx->createElement('desc');
						$gpx_desc = $gpx_wpt->appendChild($gpx_desc);
						$gpx_desc_text = $dom_gpx->createTextNode($description);
						$gpx_desc->appendChild($gpx_desc_text);

						//$gpx_sym = $dom_gpx->createElement('sym');
						//$gpx_sym = $gpx_wpt->appendChild($gpx_sym);
						//$gpx_sym_text = $dom_gpx->createTextNode('Waypoint');
						//$gpx_sym->appendChild($gpx_sym_text);

						if (count($latlng) > 2) {
							$gpx_ele = $dom_gpx->createElement('ele');
							$gpx_ele = $gpx_wpt->appendChild($gpx_ele);
							$gpx_ele_text = $dom_gpx->createTextNode($latlng[2]);
							$gpx_ele->appendChild($gpx_ele_text);
						}
					}
				}
			}
			foreach ($placemark->getElementsByTagName('Polygon') as $lineString) {
				$outbounds = $lineString->getElementsByTagName('outerBoundaryIs');
				foreach ($outbounds as $outbound) {
					foreach ($outbound->getElementsByTagName('coordinates') as $coordinates) {
						//add the new track
						$gpx_trk = $dom_gpx->createElement('trk');
						$gpx_trk = $gpx->appendChild($gpx_trk);

						$gpx_name = $dom_gpx->createElement('name');
						$gpx_name = $gpx_trk->appendChild($gpx_name);
						$gpx_name_text = $dom_gpx->createTextNode($name);
						$gpx_name->appendChild($gpx_name_text);

						$gpx_trkseg = $dom_gpx->createElement('trkseg');
						$gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

						$coordinates = trim($coordinates->nodeValue);
						$coordinates = preg_split("/[\s\r\n]+/", $coordinates); //split the coords by new line
						foreach ($coordinates as $coordinate) {
							$latlng = explode(",", $coordinate);

							if (($lat = $latlng[1]) && ($lng = $latlng[0])) {
								$gpx_trkpt = $dom_gpx->createElement('trkpt');
								$gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

								$gpx_trkpt_lat = $dom_gpx->createAttribute('lat');
								$gpx_trkpt->appendChild($gpx_trkpt_lat);
								$gpx_trkpt_lat_text = $dom_gpx->createTextNode($lat);
								$gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);

								$gpx_trkpt_lon = $dom_gpx->createAttribute('lon');
								$gpx_trkpt->appendChild($gpx_trkpt_lon);
								$gpx_trkpt_lon_text = $dom_gpx->createTextNode($lng);
								$gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);

								$gpx_time = $dom_gpx->createElement('time');
								$gpx_time = $gpx_trkpt->appendChild($gpx_time);
								$gpx_time_text = $dom_gpx->createTextNode($this->utcdate());
								$gpx_time->appendChild($gpx_time_text);

								if (count($latlng) > 2) {
									$gpx_ele = $dom_gpx->createElement('ele');
									$gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
									$gpx_ele_text = $dom_gpx->createTextNode($latlng[2]);
									$gpx_ele->appendChild($gpx_ele_text);
								}
							}
						}
					}
				}
			}
			foreach ($placemark->getElementsByTagName('LineString') as $lineString) {
				foreach ($lineString->getElementsByTagName('coordinates') as $coordinates) {
					//add the new track
					$gpx_trk = $dom_gpx->createElement('trk');
					$gpx_trk = $gpx->appendChild($gpx_trk);

					$gpx_name = $dom_gpx->createElement('name');
					$gpx_name = $gpx_trk->appendChild($gpx_name);
					$gpx_name_text = $dom_gpx->createTextNode($name);
					$gpx_name->appendChild($gpx_name_text);

					$gpx_trkseg = $dom_gpx->createElement('trkseg');
					$gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

					$coordinates = trim($coordinates->nodeValue);
					$coordinates = preg_split("/[\r\n]+/", $coordinates); //split the coords by new line
					foreach ($coordinates as $coordinate) {
						$latlng = explode(",", $coordinate);

						if (($lat = $latlng[1]) && ($lng = $latlng[0])) {
							$gpx_trkpt = $dom_gpx->createElement('trkpt');
							$gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

							$gpx_trkpt_lat = $dom_gpx->createAttribute('lat');
							$gpx_trkpt->appendChild($gpx_trkpt_lat);
							$gpx_trkpt_lat_text = $dom_gpx->createTextNode($lat);
							$gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);

							$gpx_trkpt_lon = $dom_gpx->createAttribute('lon');
							$gpx_trkpt->appendChild($gpx_trkpt_lon);
							$gpx_trkpt_lon_text = $dom_gpx->createTextNode($lng);
							$gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);

							$gpx_time = $dom_gpx->createElement('time');
							$gpx_time = $gpx_trkpt->appendChild($gpx_time);
							$gpx_time_text = $dom_gpx->createTextNode($this->utcdate());
							$gpx_time->appendChild($gpx_time_text);

							if (count($latlng) > 2) {
								$gpx_ele = $dom_gpx->createElement('ele');
								$gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
								$gpx_ele_text = $dom_gpx->createTextNode($latlng[2]);
								$gpx_ele->appendChild($gpx_ele_text);
							}
						}
					}
				}
			}
		}

		return $dom_gpx->saveXML();
	}

	public function unicsvToGpx($csvFilePath) {
		$dom_gpx = $this->createDomGpxWithHeaders();
		$gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

		$csv = array_map('str_getcsv', file($csvFilePath, FILE_SKIP_EMPTY_LINES));
		$keys = array_shift($csv);

		foreach ($csv as $i=>$row) {
			$csv[$i] = array_combine($keys, $row);
		}

		foreach ($csv as $line) {
			$lat = $line['Latitude'];
			$lon = $line['Longitude'];

			$gpx_wpt = $dom_gpx->createElement('wpt');
			$gpx_wpt = $gpx->appendChild($gpx_wpt);

			$gpx_wpt_lat = $dom_gpx->createAttribute('lat');
			$gpx_wpt->appendChild($gpx_wpt_lat);
			$gpx_wpt_lat_text = $dom_gpx->createTextNode($lat);
			$gpx_wpt_lat->appendChild($gpx_wpt_lat_text);

			$gpx_wpt_lon = $dom_gpx->createAttribute('lon');
			$gpx_wpt->appendChild($gpx_wpt_lon);
			$gpx_wpt_lon_text = $dom_gpx->createTextNode($lon);
			$gpx_wpt_lon->appendChild($gpx_wpt_lon_text);

			if (array_key_exists('Symbol', $line)) {
				$gpx_symbol = $dom_gpx->createElement('sym');
				$gpx_symbol = $gpx_wpt->appendChild($gpx_symbol);
				$gpx_symbol_text = $dom_gpx->createTextNode($line['Symbol']);
				$gpx_symbol->appendChild($gpx_symbol_text);
			}
			if (array_key_exists('Name', $line)) {
				$gpx_name = $dom_gpx->createElement('name');
				$gpx_name = $gpx_wpt->appendChild($gpx_name);
				$gpx_name_text = $dom_gpx->createTextNode($line['Name']);
				$gpx_name->appendChild($gpx_name_text);
			}

		}
		return $dom_gpx->saveXML();
	}

	public function tcxToGpx($tcxcontent) {
		$dom_tcx = new DOMDocument();
		$dom_tcx->loadXML($tcxcontent);

		$dom_gpx = $this->createDomGpxWithHeaders();
		$gpx = $dom_gpx->getElementsByTagName('gpx')->item(0);

		foreach ($dom_tcx->getElementsByTagName('Course') as $course) {
			$name = '';
			foreach ($course->getElementsByTagName('Name') as $name) {
				$name  = $name->nodeValue;
			}
			//add the new track
			$gpx_trk = $dom_gpx->createElement('trk');
			$gpx_trk = $gpx->appendChild($gpx_trk);

			$gpx_name = $dom_gpx->createElement('name');
			$gpx_name = $gpx_trk->appendChild($gpx_name);
			$gpx_name_text = $dom_gpx->createTextNode($name);
			$gpx_name->appendChild($gpx_name_text);

			foreach ($course->getElementsByTagName('Track') as $track) {

				$gpx_trkseg = $dom_gpx->createElement('trkseg');
				$gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

				foreach ($track->getElementsByTagName('Trackpoint') as $trackpoint) {

					$gpx_trkpt = $dom_gpx->createElement('trkpt');
					$gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

					foreach ($trackpoint->getElementsByTagName('Time') as $time) {
						$gpx_time = $dom_gpx->createElement('time');
						$gpx_time = $gpx_trkpt->appendChild($gpx_time);
						$gpx_time_text = $dom_gpx->createTextNode($time->nodeValue);
						$gpx_time->appendChild($gpx_time_text);
					}
					foreach ($trackpoint->getElementsByTagName('Position') as $position) {
						foreach ($trackpoint->getElementsByTagName('LatitudeDegrees') as $lat) {
							$gpx_trkpt_lat = $dom_gpx->createAttribute('lat');
							$gpx_trkpt->appendChild($gpx_trkpt_lat);
							$gpx_trkpt_lat_text = $dom_gpx->createTextNode($lat->nodeValue);
							$gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);
						}
						foreach ($trackpoint->getElementsByTagName('LongitudeDegrees') as $lon) {
							$gpx_trkpt_lon = $dom_gpx->createAttribute('lon');
							$gpx_trkpt->appendChild($gpx_trkpt_lon);
							$gpx_trkpt_lon_text = $dom_gpx->createTextNode($lon->nodeValue);
							$gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);
						}
					}
					foreach ($trackpoint->getElementsByTagName('AltitudeMeters') as $ele) {
						$gpx_ele = $dom_gpx->createElement('ele');
						$gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
						$gpx_ele_text = $dom_gpx->createTextNode($ele->nodeValue);
						$gpx_ele->appendChild($gpx_ele_text);
					}
				}
			}
		}

		foreach ($dom_tcx->getElementsByTagName('Activity') as $activity) {
			$name = '';

			//add the new track
			$gpx_trk = $dom_gpx->createElement('trk');
			$gpx_trk = $gpx->appendChild($gpx_trk);

			$gpx_name = $dom_gpx->createElement('name');
			$gpx_name = $gpx_trk->appendChild($gpx_name);
			$gpx_name_text = $dom_gpx->createTextNode($name);
			$gpx_name->appendChild($gpx_name_text);

			foreach ($activity->getElementsByTagName('Lap') as $lap) {

				foreach ($lap->getElementsByTagName('Track') as $track) {

					$gpx_trkseg = $dom_gpx->createElement('trkseg');
					$gpx_trkseg = $gpx_trk->appendChild($gpx_trkseg);

					foreach ($track->getElementsByTagName('Trackpoint') as $trackpoint) {

						$gpx_trkpt = $dom_gpx->createElement('trkpt');
						$gpx_trkpt = $gpx_trkseg->appendChild($gpx_trkpt);

						foreach ($trackpoint->getElementsByTagName('Time') as $time) {
							$gpx_time = $dom_gpx->createElement('time');
							$gpx_time = $gpx_trkpt->appendChild($gpx_time);
							$gpx_time_text = $dom_gpx->createTextNode($time->nodeValue);
							$gpx_time->appendChild($gpx_time_text);
						}
						foreach ($trackpoint->getElementsByTagName('Position') as $position) {
							foreach ($trackpoint->getElementsByTagName('LatitudeDegrees') as $lat) {
								$gpx_trkpt_lat = $dom_gpx->createAttribute('lat');
								$gpx_trkpt->appendChild($gpx_trkpt_lat);
								$gpx_trkpt_lat_text = $dom_gpx->createTextNode($lat->nodeValue);
								$gpx_trkpt_lat->appendChild($gpx_trkpt_lat_text);
							}
							foreach ($trackpoint->getElementsByTagName('LongitudeDegrees') as $lon) {
								$gpx_trkpt_lon = $dom_gpx->createAttribute('lon');
								$gpx_trkpt->appendChild($gpx_trkpt_lon);
								$gpx_trkpt_lon_text = $dom_gpx->createTextNode($lon->nodeValue);
								$gpx_trkpt_lon->appendChild($gpx_trkpt_lon_text);
							}
						}
						foreach ($trackpoint->getElementsByTagName('AltitudeMeters') as $ele) {
							$gpx_ele = $dom_gpx->createElement('ele');
							$gpx_ele = $gpx_trkpt->appendChild($gpx_ele);
							$gpx_ele_text = $dom_gpx->createTextNode($ele->nodeValue);
							$gpx_ele->appendChild($gpx_ele_text);
						}
					}
				}
			}
		}

		return $dom_gpx->saveXML();
	}
}
