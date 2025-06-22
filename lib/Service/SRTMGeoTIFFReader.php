<?php

/**
 *  Returns elevation in metres from CGIAR-CSI SRTM v4 & 4.1 GeoTIFF files given WGS84 latitude and Longitude
 *
 *  Data points are available for each 3" of arc (approx every 90m)
 *  Each data file covers a 5 degree x 5 degree area of the world's surface between 60N & 60S
 *  Files are named in the range 'srtm_01_01.tif' to 'srtm_72_24.tif'
 *
 *  See http://srtm.csi.cgiar.org for more info
 */
namespace OCA\GpxPod\Service;

use OCA\GpxPod\AppInfo\Application;
use OCP\Files\SimpleFS\ISimpleFolder;
use Psr\Log\LoggerInterface;

class SRTMGeoTIFFReader {

	public const LEN_OFFSET = 4;           // the number of bytes required to hold a TIFF offset address

	// CGIAR-CSI SRTM GeoTIFF constants
	public const DEGREES_PER_TILE = 5;     // each tile is 5 x 5 degrees of lat/lon
	public const PIXEL_DIST = 0.000833333; // the distance represented by one pixel (0 degrees 0 mins 3 secs of arc = 1/1200)
	public const PIXEL_DIST_METRES = 90;   // PIXEL_DIST in metres (approximately)
	public const NO_DATA = -32768;         // a data void is the signed short 0x8000 (-32768)

	// read/write public properties
	public $showErrors = true;     // show messages on error condition, otherwise dies silently
	public $maxPoints = 10000;      // default maximum number of multiple locations accepted

	// private properties
	private $fileName;             // name of current GeoTIFF data file
	private $fp;                   // file pointer to current GeoTIFF data file
	private $tileRefHoriz;         // the horizontal tile reference figure (01-72)
	private $tileRefVert;          // the vertical tile reference figure (01-24)
	private $latLons = [];    // the supplied lats & lons
	private $elevations = []; // the elevations values found
	private LoggerInterface $logger;
	private ISimpleFolder $dataDir;
	private mixed $stripOffsets;
	private mixed $numDataRows;
	private mixed $numDataCols;
	private int|float $topleftLon;
	private int|float $topleftLat;

	/**
	 * Constructor: assigns data directory
	 *
	 * @param ISimpleFolder $dataDir
	 * @param LoggerInterface $logger
	 */
	public function __construct(ISimpleFolder $dataDir, LoggerInterface $logger) {
		$this->logger = $logger;
		$this->dataDir = $dataDir;
	}

	/**
	 * Destructor: clean up resources
	 *
	 */
	public function __destruct() {
		if ($this->fp) {
			fclose($this->fp);
		}
	}

	/**
	 * Returns the current file name
	 */
	public function getFileName() {
		return $this->fileName;
	}

	/**
	 * Returns the number of elevations calculated
	 */
	public function getNumElevations() {
		return count($this->elevations);
	}

	/**
	 * Returns an array of total ascent & descent
	 *
	 */
	public function getAscentDescent() {

		$ascent = $descent = 0;
		$numElevations = $this->getNumElevations();
		if ($numElevations > 1) {
			for ($i = 1; $i < $numElevations; $i++) {
				$thisElev = $this->elevations[$i];
				$lastElev = $this->elevations[$i - 1];
				$diff = abs($lastElev - $thisElev);
				if (($thisElev != self::NO_DATA) && ($lastElev != self::NO_DATA)) {
					if ($diff > 0) {
						($thisElev > $lastElev) ? $ascent += $diff : $descent += $diff;
					}
				}
			}
		}
		return ['ascent' => $ascent, 'descent' => $descent];
	}

	/**
	 * Returns the total distance
	 *
	 */
	public function getTotalDistance() {

		$distance = 0;
		for ($i = 2; $i < count($this->latLons); $i += 2) {
			$distance += $this->getDistance(
				$this->latLons[$i - 2],
				$this->latLons[$i - 1],
				$this->latLons[$i],
				$this->latLons[$i + 1],
				false);
		}
		return $distance;
	}

	/**
	 * Returns the elevation in metres for a given Latitude and Longitude
	 * where N & E are positive and S & W are negative
	 * e.g. Lat 55°30'N, Lon 002°20'W is entered as (55.5, -2.333333)
	 *
	 * Set optional parameter Sinterpolate to true to for smoother graphs at the cost of 4x the reads
	 *
	 * @param float $latitude
	 * @param float $longitude
	 * @param bool $interpolate
	 */
	public function getElevation($latitude, $longitude, $interpolate = false) {

		// work out the data tile name
		if (! $this->checkTileInfo($latitude, $longitude)) {

			// it's not the same tile as the last run, so get the new tile and file name
			$fileName = $this->getTileFileName($this->tileRefHoriz, $this->tileRefVert);

			// read the file and jump to the first data address
			$this->getSRTMFilePointer($fileName);
		}

		if ($interpolate) {
			// use smoother but slower bilinear interpolation method
			$elevation = $this->getInterpolatedElevation($latitude, $longitude);
		} else {
			// use faster rounding method
			$elevation = $this->getRoundedElevation($latitude, $longitude);
		}

		return $elevation;
	}

	/**
	 * Returns an array of elevations in metres given an array of lats & lons
	 * as {lat1, lon1, ... latn, lonn}. Can optionally calculate intermediate locations at
	 * 3" intervals and optionally use bilinear interpolation
	 *
	 * @param array $latLons
	 * @param bool $addIntermediatelatLons
	 * @param bool $interpolate
	 * @return array
	 */
	public function getMultipleElevations(array $latLons, bool $addIntermediatelatLons = true, bool $interpolate = false): array {
		$totNumSteps = 0;
		$numlatLons = count($latLons);

		if ($numlatLons < 4) {
			$this->handleError(__METHOD__, 'need at least two point locations in the latLons array');
		}

		// bale out if limit is reached
		$limit = $this->maxPoints;
		if (($numlatLons / 2) > $limit) {
			$this->handleError(__METHOD__, "maximum number of allowed point locations ($limit) exceeded");
		}

		if (($numlatLons % 2) != 0) {
			$this->handleError(__METHOD__, 'uneven number of lat and lon params ');
		}

		if ($addIntermediatelatLons) {
			// work out intermediate lats and lons for every 3" of arc
			for ($i = 2; $i < $numlatLons; $i += 2) {

				$startLat = $latLons[$i - 2];
				$endLat = $latLons[$i];
				$dlat = $endLat - $startLat;

				$startLon = $latLons[$i - 1];
				$endLon = $latLons[$i + 1];
				$dlon = $endLon - $startLon;

				// get the distance in metres between the two locations
				$dist = $this->getDistance($startLat, $startLon, $endLat, $endLon, false) * 1000;
				if ($dist > self::PIXEL_DIST_METRES) {

					// get the number of intermediate locations to
					// generate between the two locations
					$stepDiff = $dist / self::PIXEL_DIST_METRES;
					$numSteps = ceil($stepDiff) - 1;

					// calculate the approximate intermediate positions
					// by simple proportion of dlat and dlon
					$totNumSteps += $numSteps;
					if ($totNumSteps >= $limit) {
						$this->handleError(__METHOD__, "maximum number of allowed point locations ($limit) exceeded while calculating intermediate points");
					}

					for ($j = 0; $j < $numSteps; $j++) {
						$midLat = $startLat + ($j * $dlat / $stepDiff) ;
						$midLon = $startLon + ($j * $dlon / $stepDiff);
						$elevations[] = $this->getElevation($midLat, $midLon, $interpolate);
					}
				}
			}
			$elevations[] = $this->getElevation($endLat, $endLon, $interpolate);
		} else {
			// just do the provided lats and lons, no intermediate positions are calculated
			for ($k = 0; $k < $numlatLons; $k += 2) {
				$elevations[] = $this->getElevation($latLons[$k], $latLons[$k + 1], $interpolate);
			}
		}

		$this->elevations = $elevations;
		$this->latLons = $latLons;

		return $elevations;
	}

	/**
	 * Returns the elevation in metres for a given tile and zero-based row and column
	 * e.g. for 7th row, 4th col in 'srtm_36_02.tif', call getElevationByRowCol(36, 2, 6, 3)
	 * Used only for testing, sanity checking and comparing to ASCII version of data
	 *
	 * @param int horizTileRef
	 * @param int vertTileRef
	 * @param int row
	 * @param int col
	 */
	public function getElevationByRowCol($tileRefHoriz, $tileRefVert, $row, $col) {

		// get the data file name and the lat & long values in the top left-hand corner
		// then read the file and jump to the first data address
		$fileName = $this->getTileFileName($tileRefHoriz, $tileRefVert);
		$this->getSRTMFilePointer($fileName);

		// get the elevation for the given row & column
		$elevation = $this->getRowColumnData($row, $col);
		if (!$elevation) {
			$elevation = self::NO_DATA;
		}
		return "Row: $row, Col: $col. Elevation: $elevation m<br>";
	}

	/**
	 * Returns the elevation value of the single data point which is closest to the parameter point
	 *
	 * @param float $latitude
	 * @param float $longitude
	 */
	private function getRoundedElevation(float $latitude, float $longitude) {
		// Returns results exactly as per http://www.geonames.org elevation API

		$row = round(($this->topleftLat - $latitude) / self::DEGREES_PER_TILE * ($this->numDataRows - 1));
		$col = round(abs($this->topleftLon - $longitude) / self::DEGREES_PER_TILE * ($this->numDataCols - 1));

		// get the elevation for the calculated row & column
		return $this->getRowColumnData((int)$row, (int)$col);
	}

	/**
	 * Returns the elevation of the parameter point by performing a bilinear interpolation
	 * of the elevation values of the four data points which surround the parameter point
	 *
	 * @param float $latitude
	 * @param float $longitude
	 */
	private function getInterpolatedElevation($latitude, $longitude) {
		// calculate row & col for the data point p0 (above & left of the parameter point)
		$row[0] = floor(($this->topleftLat - $latitude) / self::DEGREES_PER_TILE * ($this->numDataRows - 1));
		$col[0] = floor(abs($this->topleftLon - $longitude) / self::DEGREES_PER_TILE * ($this->numDataCols - 1));

		// set row & col for the data point p1 (above & right of the parameter point)
		$row[1] = $row[0];
		$col[1] = $col[0] + 1;

		// set row & col for the data point p2 (below & left of the parameter point)
		$row[2] = $row[0] + 1;
		$col[2] = $col[0];

		// set row & col for the data point p3 (below & right of the parameter point)
		$row[3] = $row[0] + 1;
		$col[3] = $col[0] + 1;

		// get the difference in lat & lon between the p0 data point and the parameter point
		$dlat = $this->topleftLat - ($row[0] * self::PIXEL_DIST) - abs($latitude);
		$dlon = $this->topleftLon + ($col[0] * self::PIXEL_DIST) - $longitude;

		// express dlat & dlon as a proportion of the side of the square created by p0, p1, p2, p3
		$dlatProportion = abs($dlat / self::PIXEL_DIST);
		$dlonProportion = abs($dlon / self::PIXEL_DIST);

		// get the elevation values for points p0, p1, p2 & p3
		$noData = false;
		for ($i = 0; $i < 4; $i++) {
			$elev = $this->getRowColumnData($row[$i], $col[$i]);
			if ($elev == self::NO_DATA) {
				$noData = true;
			}
			$points[] = $elev;
		}

		// interpolate between the four elevation values
		if (!$noData) {
			$elevation = self::interpolate($dlonProportion, $dlatProportion, $points);
		} else {
			$elevation = self::NO_DATA;
		}
		return $elevation;
	}

	/**
	 * Returns the value for point P located inside the square formed by four data points
	 * by performing a bilinear interpolation of the four data values
	 *
	 * @param float $x
	 * @param float $y
	 * @param array $pointData
	 */
	private function interpolate($x, $y, $pointData) {
		// NB: x & y are expressed as a proportions of the dimension of the square side

		// p0------------p1
		// |      |
		// |      y
		// |      |
		// |--x-- .P
		// |
		// p2------------p3

		// bilinear interpolation formula
		// https://en.wikipedia.org/wiki/Bilinear_interpolation#Unit_Square
		// where p2 = (0,0), p3 = (1,0), p0 = (0,1), p1 = (1,1)
		$val = $pointData[2] * (1 - $x) * (1 - $y)
			  + $pointData[3] * $x * (1 - $y)
			  + $pointData[0] * $y * (1 - $x)
			  + $pointData[1] * $x * $y;

		return sprintf('%0.1f', $val);
	}

	/**
	 * Saves the horizontal & vertical tile identifer numbers plus lat & long values
	 * of the the top left-hand corner of the tile to class vars
	 *
	 * Returns true if the current tile is the same as the last-used tile
	 *
	 * @param float $lat
	 * @param float $lon
	 */
	private function checkTileInfo($lat, $lon) {

		$MAX_LAT = 60; // maximum N & S latitude for which data is available

		// NB: gets the values of the top left lat and lon (row 0, col 0)
		if (($lat > - $MAX_LAT) && ($lat <= $MAX_LAT)) {
			$tileRefVert = (fmod($lat, self::DEGREES_PER_TILE) == 0)
				? (($MAX_LAT - $lat) / self::DEGREES_PER_TILE + 1)
				: (ceil(($MAX_LAT - $lat) / self::DEGREES_PER_TILE));

			$topleftLat = $MAX_LAT - (($tileRefVert - 1) * self::DEGREES_PER_TILE) ;
		} else {
			$this->handleError(__METHOD__, "latitude ($lat) out of range");
		}

		if (($lon > - 180) && ($lon < 180)) {
			$tileRefHoriz = (fmod($lon, self::DEGREES_PER_TILE) == 0)
				? ((180 + $lon) / self::DEGREES_PER_TILE + 1)
				: (ceil((180 + $lon) / self::DEGREES_PER_TILE));

			$topleftLon = (($tileRefHoriz - 1) * self::DEGREES_PER_TILE) - 180;
		} else {
			$this->handleError(__METHOD__, "longitude ($lon) out of range");
		}

		$sameFile = false;
		if (($this->tileRefHoriz == $tileRefHoriz) && ($this->tileRefVert == $tileRefVert)) {
			$sameFile = true;
		}

		$this->tileRefHoriz = $tileRefHoriz;
		$this->tileRefVert = $tileRefVert;
		$this->topleftLat = $topleftLat;
		$this->topleftLon = $topleftLon;

		return $sameFile;
	}

	/**
	 * @param float $lat
	 * @param float $lon
	 * @return float[]|null
	 */
	public static function getTileInfo(float $lat, float $lon): ?array {
		$MAX_LAT = 60; // maximum N & S latitude for which data is available

		// NB: gets the values of the top left lat and lon (row 0, col 0)
		if (($lat > - $MAX_LAT) && ($lat <= $MAX_LAT)) {
			$tileRefVert = (fmod($lat, self::DEGREES_PER_TILE) === 0.0)
				? (($MAX_LAT - $lat) / self::DEGREES_PER_TILE + 1)
				: (ceil(($MAX_LAT - $lat) / self::DEGREES_PER_TILE));
		} else {
			return null;
		}

		if (($lon > - 180) && ($lon < 180)) {
			$tileRefHoriz = (fmod($lon, self::DEGREES_PER_TILE) === 0.0)
				? ((180 + $lon) / self::DEGREES_PER_TILE + 1)
				: (ceil((180 + $lon) / self::DEGREES_PER_TILE));
		} else {
			return null;
		}

		return [
			'horiz' => $tileRefHoriz,
			'vert' => $tileRefVert,
		];
	}

	/**
	 * Returns a file name given the vertical and horizontal identifiers
	 * in the format used by CGIAR-CSI SRTM GeoTIFF v4, e.g. 'srtm_hh_vv.tif'
	 *
	 * @param int $tileRefHoriz
	 * @param int $tileRefVert
	 */
	private function getTileFileName($tileRefHoriz, $tileRefVert) {

		$fileName = 'srtm_'
				   . str_pad((string)$tileRefHoriz, 2, '0', STR_PAD_LEFT)
				   . '_'
				   . str_pad((string)$tileRefVert, 2, '0', STR_PAD_LEFT)
				   . '.tif';

		return $fileName;
	}

	/**
	 * Read the data file and get a pointer to the first data offset
	 *
	 * @param string $fileName
	 */
	private function getSRTMFilePointer($fileName) {

		// standard TIFF constants
		$TIFF_ID = 42;             // magic number located at bytes 2-3 which identifies a TIFF file
		$TAG_STRIPOFFSETS = 273;   // identifying code for 'StripOffsets' tag in the Image File Directory (IFD)
		$TAG_IMAGE_WIDTH = 256;
		$TAG_IMAGE_LENGTH = 257;
		$LEN_IFD_FIELD = 12;       // the number of bytes in each IFD entry
		$BIG_ENDIAN = 'MM';        // byte order identifiers located at bytes 0-1
		$LITTLE_ENDIAN = 'II';

		// close any previous file pointer
		if ($this->fp) {
			fclose($this->fp);
		}
		if (!$this->dataDir->fileExists($fileName)) {
			$this->logger->warning('SRTM file does not exist: ' . $fileName, ['app' => Application::APP_ID]);
		}
		$fp = $this->dataDir->getFile($fileName)->read();
		if ($fp === false) {
			$this->logger->warning('Could not open the SRTM file: ' . $fileName, ['app' => Application::APP_ID]);
		}

		// go to the file header and work out the byte order (bytes 0-1)
		// and TIFF identifier (bytes 2-3)
		fseek($fp, 0);
		$dataBytes = fread($fp, 4);
		$data = unpack('c2chars/vTIFF_ID', $dataBytes);

		// check it's a valid TIFF file by looking for the magic number
		$TIFF = $data['TIFF_ID'];
		if ($TIFF != $TIFF_ID) {
			$this->handleError(__METHOD__, "the file '$fileName' is not a valid TIFF file");
		}

		// convert the byte order code to ASCII to get Motorola or Intel ordering identifiers
		$byteOrder = sprintf('%c%c', $data['chars1'], $data['chars2']);

		// the remaining 4 bytes in the header are the offset to the IFD
		fseek($fp, 4);
		$dataBytes = fread($fp, 4);
		// unpack in whichever byte order was identified previously
		// - this seems to be always 'II' but whether this is always the case is not specified
		// so we do the check each time to make sure
		if ($byteOrder == $LITTLE_ENDIAN) {
			$data = unpack('VIFDoffset', $dataBytes);
		} elseif ($byteOrder == $BIG_ENDIAN) {
			$data = unpack('NIFDoffset', $dataBytes);
		} else {
			self::handleError(__METHOD__, "could not determine the byte order of the file '$fileName'");
		}

		// now jump to the IFD offset and get the number of entries in the IFD
		// which is always stored in the first two bytes of the IFD
		fseek($fp, $data['IFDoffset']);
		$dataBytes = fread($fp, 2) ;
		$data = ($byteOrder == $LITTLE_ENDIAN)
			? unpack('vcount', $dataBytes)
			: unpack('ncount', $dataBytes);
		$numFields = $data['count'];

		// iterate the IFD entries until we find the ones we need
		for ($i = 0; $i < $numFields; $i++) {
			$dataBytes = fread($fp, $LEN_IFD_FIELD);
			$data = ($byteOrder == $LITTLE_ENDIAN)
				? unpack('vtag/vtype/Vcount/Voffset', $dataBytes)
				: unpack('ntag/ntype/Ncount/Noffset', $dataBytes);

			switch ($data['tag']) {
				case $TAG_IMAGE_WIDTH:
					$this->numDataCols = $data['offset'];
					break;
				case $TAG_IMAGE_LENGTH:
					$this->numDataRows = $data['offset'];
					break;
				case $TAG_STRIPOFFSETS:
					$this->stripOffsets = $data['offset'];
					break;
			}
		}

		$this->fileName = $fileName;
		$this->fp = $fp;
	}

	/**
	 * Returns the elevation data at a given zero-based row and column
	 * using the current file pointer
	 *
	 * @param int $row
	 * @param int $col
	 */
	private function getRowColumnData(int $row, int $col) {

		$LEN_DATA = 2;             // the number of bytes containing each item of elevation data
		// ( = BitsPerSample tag value / 8)

		// find the location of the required data row in the StripOffsets data
		$dataOffset = $this->stripOffsets + ($row * self::LEN_OFFSET);
		fseek($this->fp, $dataOffset);
		$dataBytes = fread($this->fp, self::LEN_OFFSET);
		$data = unpack('VdataOffset', $dataBytes);

		// this is the offset of the 1st column in the required data row
		$firstColOffset = $data['dataOffset'];

		// now work out the required column offset relative to the 1st column
		$requiredColOffset = $col * $LEN_DATA;

		// combine the two and read the elevation data at that address
		fseek($this->fp, $firstColOffset + $requiredColOffset);
		$dataBytes = fread($this->fp, $LEN_DATA);
		$data = unpack('selevation', $dataBytes);

		$elevation = $data['elevation'];
		return $elevation;
	}

	/**
	 * Returns the distance between two location using the Haversine formula
	 * in either miles or kilometres
	 *
	 * @param float $lat1
	 * @param float $lon1
	 * @param float $lat2
	 * @param float $lon2
	 * @param mixed $miles
	 * @return float
	 */
	private function getDistance($lat1, $lon1, $lat2, $lon2, $miles = true) {

		// earth's diameter in miles and kilometres
		$miles ? $earth = 3960 :  $earth = 6371;

		$lat1 = deg2rad($lat1);
		$lon1 = deg2rad($lon1);

		$lat2 = deg2rad($lat2);
		$lon2 = deg2rad($lon2);

		$dlon = $lon2 - $lon1;
		$dlat = $lat2 - $lat1;

		// Haversine Formula
		$sinlat = sin($dlat / 2);
		$sinlon = sin($dlon / 2);
		$a = ($sinlat * $sinlat) + cos($lat1) * cos($lat2) * ($sinlon * $sinlon);
		$c = 2 * asin(min(1, sqrt($a)));
		$d = $earth * $c;
		return $d;
	}

	/**
	 * Error handler
	 *
	 * @param string $error
	 */
	private function handleError($method, $message) {
		if ($this->showErrors) {
			ob_start();
			// var_dump($this);
			$dump = ob_get_contents();
			ob_end_clean();
			die("Died: error in $method: $message <pre>$dump</pre>");
		} else {
			die();
		}
	}
}
