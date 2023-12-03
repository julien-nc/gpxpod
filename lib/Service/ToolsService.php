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

use DOMDocument;

class ToolsService {

	public function __construct () {
	}

	/**
	 * @param string $name
	 * @return DOMDocument
	 * @throws \DOMException
	 */
	public function createDomKmlWithHeaders(string $name): DOMDocument {
		$domKml = new DOMDocument('1.0', 'UTF-8');
		$domKml->formatOutput = true;

		$kml = $domKml->appendChild(
			$domKml->createElement('kml')
		);

		$kml->appendChild($domKml->createAttribute('xmlns'))
			->appendChild($domKml->createTextNode('http://www.opengis.net/kml/2.3'));
		$kml->appendChild($domKml->createAttribute('xmlns:atom'))
			->appendChild($domKml->createTextNode('http://www.w3.org/2005/Atom'));

		$document = $kml->appendChild($domKml->createElement('Document'));

		$document->appendChild($domKml->createElement('open'))->appendChild($domKml->createTextNode('1'));
		$document->appendChild($domKml->createElement('visibility'))->appendChild($domKml->createTextNode('1'));
		$document->appendChild($domKml->createElement('name'))->appendChild($domKml->createTextNode($name));
		$document->appendChild($domKml->createElement('atom:generator'))
			->appendChild($domKml->createTextNode('Nextcloud GpxPod'));

		return $domKml;
	}

	/**
	 * @return DOMDocument
	 * @throws \DOMException
	 */
	public function createDomGpxWithHeaders(): DOMDocument {
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

	/**
	 * @param string $content
	 * @return string
	 */
	public function sanitizeGpxContent(string $content): string {
		// if we have something like
		// <time>2022-03-27T15:32:37.504+02:00[Europe/Brussels]</time>
		// this does not work if the string exceeds the php limit, preg_replace will return null
		// in this case we return the raw string
		return preg_replace('/(<time>.*)\[[^]]*\](<\/time>)/', '$1$2', $content) ?? $content;
	}

	public function utcdate() {
		return gmdate('Y-m-d\Th:i:s\Z');
	}

	public function remove_utf8_bom(string $text): string {
		$bom = pack('H*','EFBBBF');
		$text = preg_replace("/^$bom/", '', $text);
		return $text;
	}

	public function encodeURIComponent(string $str): string {
		$revert = ['%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')'];
		return strtr(rawurlencode($str), $revert);
	}

	public function formatTimeSeconds(int $time_s): string {
		$minutes = floor($time_s / 60);
		$hours = floor($minutes / 60);

		return sprintf('%02d:%02d:%02d', $hours, $minutes % 60, $time_s % 60);
	}

	public function delTree(string $dir): bool {
		$files = array_diff(scandir($dir), array('.','..'));
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}

	/**
	 * Recursive find files from name pattern
	 */
	public function globRecursive(string $path, string $find, bool $recursive = true): array {
		$result = [];
		$dh = opendir($path);
		while (($file = readdir($dh)) !== false) {
			if (substr($file, 0, 1) === '.') continue;
			$rfile = "{$path}/{$file}";
			if (is_dir($rfile) && $recursive) {
				foreach ($this->globRecursive($rfile, $find) as $ret) {
					$result[] = $ret;
				}
			} else {
				if (fnmatch($find, $file)){
					$result[] = $rfile;
				}
			}
		}
		closedir($dh);
		return $result;
	}

	private function isParentOf(string $parentPath, string $childPath): bool {
		return str_starts_with($childPath, $parentPath);
	}

	/*
	 * search into all directories in PATH environment variable
	 * to find a program and return it if found
	 */
	public function getProgramPath(string $progname): ?string {
		$pathArray = explode(PATH_SEPARATOR, getenv('path'));
		$pathArray = array_merge($pathArray, explode(PATH_SEPARATOR, getenv('PATH')));
		$filteredPath = $pathArray;
		// filter path values with open_basedir
		$obd = ini_get('open_basedir');
		if ($obd !== null && $obd !== '') {
			$filteredPath = [];
			$obdArray = explode(PATH_SEPARATOR, $obd);
			foreach ($obdArray as $obdElem) {
				foreach ($pathArray as $pathElem) {
					if ($this->isParentOf($obdElem, $pathElem)) {
						$filteredPath[] = $pathElem;
					}
				}
			}
		}

		// now find the program path
		foreach ($filteredPath as $path) {
			$supposed_gpath = $path . '/' . $progname;
			if (file_exists($supposed_gpath) && is_executable($supposed_gpath)) {
				return $supposed_gpath;
			}
		}
		return null;
	}
}
