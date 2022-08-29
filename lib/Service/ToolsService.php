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

class ToolsService {

	public function __construct () {
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

	private function startsWith(string $haystack, string $needle): bool {
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}

	private function isParentOf(string $parentPath, string $childPath): bool {
		return $this->startsWith($childPath, $parentPath);
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
						array_push($filteredPath, $pathElem);
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

	public function endswith(string $string, string $test): bool {
		$strlen = strlen($string);
		$testlen = strlen($test);
		if ($testlen > $strlen) return false;
		return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
	}
}
