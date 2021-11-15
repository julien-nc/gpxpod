<?php

function remove_utf8_bom(string $text): string {
	$bom = pack('H*','EFBBBF');
	$text = preg_replace("/^$bom/", '', $text);
	return $text;
}

function encodeURIComponent(string $str): string {
	$revert = ['%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')'];
	return strtr(rawurlencode($str), $revert);
}

function format_time_seconds(int $time_s): string {
	$minutes = floor($time_s / 60);
	$hours = floor($minutes / 60);

	return sprintf('%02d:%02d:%02d', $hours, $minutes % 60, $time_s % 60);
}

/*
 * return distance between these two gpx points in meters
 */
function distance($p1, $p2): float {
	$lat1 = (float) $p1['lat'];
	$long1 = (float) $p1['lon'];
	$lat2 = (float) $p2['lat'];
	$long2 = (float) $p2['lon'];

	if ($lat1 === $lat2 && $long1 === $long2){
		return 0;
	}

	// Convert latitude and longitude to
	// spherical coordinates in radians.
	$degrees_to_radians = pi()/180.0;

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

function delTree(string $dir): bool {
	$files = array_diff(scandir($dir), array('.','..'));
	foreach ($files as $file) {
		(is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
	}
	return rmdir($dir);
}

/**
 * Recursive find files from name pattern
 */
function globRecursive(string $path, string $find, bool $recursive = true): array {
	$result = [];
	$dh = opendir($path);
	while (($file = readdir($dh)) !== false) {
		if (substr($file, 0, 1) === '.') continue;
		$rfile = "{$path}/{$file}";
		if (is_dir($rfile) && $recursive) {
			foreach (globRecursive($rfile, $find) as $ret) {
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

function startsWith(string $haystack, string $needle): bool {
	$length = strlen($needle);
	return (substr($haystack, 0, $length) === $needle);
}

function isParentOf(string $parentPath, string $childPath): bool {
	return startsWith($childPath, $parentPath);
}

/*
 * search into all directories in PATH environment variable
 * to find a program and return it if found
 */
function getProgramPath(string $progname): ?string {
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
				if (isParentOf($obdElem, $pathElem)) {
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

function endswith(string $string, string $test): bool {
	$strlen = strlen($string);
	$testlen = strlen($test);
	if ($testlen > $strlen) return false;
	return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

?>
