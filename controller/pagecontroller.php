<?php
/**
 * ownCloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@gmx.fr>
 * @copyright Julien Veyssier 2015
 */

namespace OCA\GpxPod\Controller;

use OCP\App\IAppManager;

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

// get decimal coordinate from exif data
function getDecimalCoords($exifCoord, $hemi) {
    $degrees = count($exifCoord) > 0 ? exifCoordToNumber($exifCoord[0]) : 0;
    $minutes = count($exifCoord) > 1 ? exifCoordToNumber($exifCoord[1]) : 0;
    $seconds = count($exifCoord) > 2 ? exifCoordToNumber($exifCoord[2]) : 0;

    $flip = ($hemi === 'W' or $hemi === 'S') ? -1 : 1;

    return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
}

// parse the coordinate string to calculate the float value
function exifCoordToNumber($coordPart) {
    $parts = explode('/', $coordPart);

    if (count($parts) <= 0)
        return 0;

    if (count($parts) === 1)
        return $parts[0];

    return floatval($parts[0]) / floatval($parts[1]);
}

function format_time_seconds($time_s){
    $minutes = floor($time_s / 60);
    $hours = floor($minutes / 60);

    return sprintf('%02d:%02d:%02d', $hours, $minutes % 60, $time_s % 60);
}

/*
 * return distance between these two gpx points in meters
 */
function distance($p1, $p2){

    $lat1 = (float)$p1['lat'];
    $long1 = (float)$p1['lon'];
    $lat2 = (float)$p2['lat'];
    $long2 = (float)$p2['lon'];

    if ($lat1 === $lat2 and $long1 === $long2){
        return 0;
    }

    // Convert latitude and longitude to
    // spherical coordinates in radians.
    $degrees_to_radians = pi()/180.0;

    // phi = 90 - latitude
    $phi1 = (90.0 - $lat1)*$degrees_to_radians;
    $phi2 = (90.0 - $lat2)*$degrees_to_radians;

    // theta = longitude
    $theta1 = $long1*$degrees_to_radians;
    $theta2 = $long2*$degrees_to_radians;

    // Compute spherical distance from spherical coordinates.

    // For two locations in spherical coordinates
    // (1, theta, phi) and (1, theta, phi)
    // cosine( arc length ) =
    //    sin phi sin phi' cos(theta-theta') + cos phi cos phi'
    // distance = rho * arc length

    $cos = (sin($phi1)*sin($phi2)*cos($theta1 - $theta2) +
           cos($phi1)*cos($phi2));
    // why some cosinus are > than 1 ?
    if ($cos > 1.0){
        $cos = 1.0;
    }
    $arc = acos($cos);

    // Remember to multiply arc by the radius of the earth
    // in your favorite set of units to get length.
    return $arc*6371000;
}

function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

/**
 * Recursive find files from name pattern
 */
function globRecursive($path, $find, $recursive=True) {
    $result = Array();
    $dh = opendir($path);
    while (($file = readdir($dh)) !== false) {
        if (substr($file, 0, 1) === '.') continue;
        $rfile = "{$path}/{$file}";
        if (is_dir($rfile) and $recursive) {
            foreach (globRecursive($rfile, $find) as $ret) {
                array_push($result, $ret);
            }
        } else {
            if (fnmatch($find, $file)){
                array_push($result, $rfile);
            }
        }
    }
    closedir($dh);
    return $result;
}

/*
 * search into all directories in PATH environment variable
 * to find a program and return it if found
 */
function getProgramPath($progname){
    $path_ar = explode(':',getenv('path'));
    $path_ar = array_merge($path_ar, explode(':',getenv('PATH')));
    foreach ($path_ar as $path){
        $supposed_gpath = $path.'/'.$progname;
        if (file_exists($supposed_gpath) and
            is_executable($supposed_gpath)){
            return $supposed_gpath;
        }
    }
    return null;
}

function endswith($string, $test) {
    $strlen = strlen($string);
    $testlen = strlen($test);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $test, $strlen - $testlen, $testlen) === 0;
}

class PageController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $appVersion;
    private $userAbsoluteDataPath;
    private $shareManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $appPath;
    private $extensions;
    private $upperExtensions;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager, IAppManager $appManager){
        parent::__construct($AppName, $request);
        $this->appVersion = $config->getAppValue('gpxpod', 'installed_version');
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('gpxpod');
        }
        else {
            $this->appPath = \OC_App::getAppPath('gpxpod');
            // even dirtier
            //$this->appPath = getcwd().'/apps/gpxpod';
        }
        $this->userId = $UserId;
        $this->dbtype = $config->getSystemValue('dbtype');
        // IConfig object
        $this->config = $config;

        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '';
        }
        $this->dbconnection = \OC::$server->getDatabaseConnection();
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
            // absolute path to user files folder
            $this->userAbsoluteDataPath =
                $this->config->getSystemValue('datadirectory').
                rtrim($this->userfolder->getFullPath(''), '/');
        }
        //$this->shareManager = \OC::$server->getShareManager();
        $this->shareManager = $shareManager;

        $this->extensions = Array(
            '.kml'=>'kml',
            '.gpx'=>'',
            '.tcx'=>'gtrnctr',
            '.igc'=>'igc',
            '.fit'=>'garmin_fit'
        );
        $this->upperExtensions = array_map("strtoupper", array_keys($this->extensions));
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    private function getUserTileServers(){
        // custom tile servers management
        $sqlts = 'SELECT servername, url FROM *PREFIX*gpxpod_tile_servers ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).';';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $tss = Array();
        while ($row = $req->fetch()){
            $tss[$row["servername"]] = $row["url"];
        }
        $req->closeCursor();
        return $tss;
    }

    /**
     * Welcome page.
     * Get list of interesting folders (containing gpx/kml/tcx files)
     * Determine if "gpxelevations" is found to give extra scan options
     * to the view.
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index() {
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();
        $gpxcomp_root_url = "gpxvcomp";
        $gpxedit_version = $this->config->getAppValue('gpxedit', 'installed_version');

        $this->cleanDbFromAbsentFiles(null);

        // DIRS array population
        $all = Array();
        foreach($this->extensions as $ext => $gpsbabel_fmt){
            $files = $userFolder->search($ext);
            $all = array_merge($all, $files);
        }
        $alldirs = Array();
        foreach($all as $file){
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                // name extension is supported
                (
                    in_array( '.'.pathinfo($file->getName(), PATHINFO_EXTENSION), array_keys($this->extensions)) or
                    in_array( '.'.pathinfo($file->getName(), PATHINFO_EXTENSION), $this->upperExtensions)
                )
            ){
                $rel_dir = str_replace($userfolder_path, '', dirname($file->getPath()));
                $rel_dir = str_replace('//', '/', $rel_dir);
                if ($rel_dir === ''){
                    $rel_dir = '/';
                }
                if (!in_array($rel_dir, $alldirs)){
                    array_push($alldirs, $rel_dir);
                }
            }
        }

        $extraScanType = Array();
        $gpxelePath = getProgramPath('gpxelevations');
        if ($gpxelePath !== null){
            $extraScanType = Array(
                'newsrtm'=>'Process new files only, correct elevations with SRTM data',
                'newsrtms'=>'Process new files only, correct and smooth elevations with SRTM data',
                'srtm'=>'Process all files, correct elevations with SRTM data',
                'srtms'=>'Process all files, correct and smooth elevations with SRTM data'
            );
        }

        $tss = $this->getUserTileServers();

        $extraSymbolList = $this->getExtraSymbolList();

        // PARAMS to view

        sort($alldirs);
        $params = [
            'dirs'=>$alldirs,
            'gpxcomp_root_url'=>$gpxcomp_root_url,
            'username'=>$this->userId,
            'extra_scan_type'=>$extraScanType,
            'tileservers'=>$tss,
            'publicgpx'=>'',
            'publicmarker'=>'',
            'publicdir'=>'',
            'pictures'=>'',
            'token'=>'',
            'gpxedit_version'=>$gpxedit_version,
            'extrasymbols'=>$extraSymbolList,
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            //->allowEvalScript('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * returns extra symbol names found in gpxedit data
     */
    private function getExtraSymbolList(){
        // extra symbols
        $gpxEditDataDirPath = $this->config->getSystemValue('datadirectory').'/gpxedit';
        $extraSymbolList = Array();
        if (is_dir($gpxEditDataDirPath.'/symbols')){
            foreach(globRecursive($gpxEditDataDirPath.'/symbols', '*.png', False) as $symbolfile){
                $filename = basename($symbolfile);
                array_push($extraSymbolList, Array('smallname'=>str_replace('.png', '', $filename), 'name'=>$filename));
            }
        }
        return $extraSymbolList;
    }

    /**
     * Ajax gpx retrieval
     * @NoAdminRequired
     */
    public function getgpx($title, $folder) {
        $userFolder = \OC::$server->getUserFolder();

        $path = $folder.'/'.$title;
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);
        $gpxContent = '';
        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE){
                if (endswith($file->getName(), '.GPX') or endswith($file->getName(), '.gpx')){
                    $gpxContent = $file->getContent();
                }
            }
        }

        $response = new DataResponse(
            [
                'content'=>$gpxContent
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * Ajax gpx retrieval
     * @NoAdminRequired
     * @PublicPage
     */
    public function getpublicgpx($title, $folder, $username) {
        $userFolder = \OC::$server->getUserFolder($username);

        $path = $folder.'/'.$title;
        $cleanpath = str_replace(array('../', '..\\'), '',  $path);
        $gpxContent = '';
        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);

            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE){
                if (endswith($file->getName(), '.GPX') or endswith($file->getName(), '.gpx')){
                    // we check the file is actually shared by public link
                    $dl_url = $this->getPublinkDownloadURL($file, $username);

                    if ($dl_url !== null){
                        $gpxContent = $file->getContent();
                    }
                }
            }
        }

        $response = new DataResponse(
            [
                'content'=>$gpxContent
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /* return marker string that will be used in the web interface
     *   each marker is : [x,y,filename,distance,duration,datebegin,dateend,poselevation,negelevation]
     */
    private function getMarkerFromFile($filepath){
        $DISTANCE_BETWEEN_SHORT_POINTS = 300;
        $STOPPED_SPEED_THRESHOLD = 0.9;

        $name = basename($filepath);
        $gpx_content = file_get_contents($filepath);

        $lat = '0';
        $lon = '0';
        $total_distance = 0;
        $total_duration = 'null';
        $date_begin = null;
        $date_end = null;
        $pos_elevation = 0;
        $neg_elevation = 0;
        $min_elevation = null;
        $max_elevation = null;
        $max_speed = 0;
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
        $shortPointList = Array();
        $lastShortPoint = null;
        $trackNameList = '[';

        $isGoingUp = False;
        $lastDeniv = null;
        $upBegin = null;
        $downBegin = null;
        $lastTime = null;

        try{
            $gpx = new \SimpleXMLElement($gpx_content);
        }
        catch (\Exception $e) {
            error_log("Exception in ".$name." gpx parsing : ".$e->getMessage());
            return null;
        }

        if (count($gpx->trk) === 0 and count($gpx->rte) === 0 and count($gpx->wpt) === 0){
            error_log('Nothing to parse in '.$name.' gpx file');
            return null;
        }

        // TRACKS
        foreach($gpx->trk as $track){
            $trackname = $track->name;
            if (empty($trackname)){
                $trackname = '';
            }
            $trackNameList .= sprintf('"%s",', $trackname);
            foreach($track->trkseg as $segment){
                $lastPoint = null;
                $lastTime = null;
                $pointIndex = 0;
                $lastDeniv = null;
                foreach($segment->trkpt as $point){
                    if (empty($point->ele)){
                        $pointele = null;
                    }
                    else{
                        $pointele = (float)$point->ele;
                    }
                    if (empty($point->time)){
                        $pointtime = null;
                    }
                    else{
                        $pointtime = new \DateTime($point->time);
                    }
                    if ($lastPoint !== null and (!empty($lastPoint->ele))){
                        $lastPointele = (float)$lastPoint->ele;
                    }
                    else{
                        $lastPointele = null;
                    }
                    if ($lastPoint !== null and (!empty($lastPoint->time))){
                        $lastTime = new \DateTime($lastPoint->time);
                    }
                    else{
                        $lastTime = null;
                    }
                    if ($lastPoint !== null){
                        $distToLast = distance($lastPoint, $point);
                    }
                    else{
                        $distToLast = null;
                    }
                    $pointlat = (float)$point['lat'];
                    $pointlon = (float)$point['lon'];
                    if ($pointIndex === 0){
                        if ($lat === '0' and $lon === '0'){
                            $lat = $pointlat;
                            $lon = $pointlon;
                        }
                        if ($pointtime !== null and ($date_begin === null or $pointtime < $date_begin)){
                            $date_begin = $pointtime;
                        }
                        $downBegin = $pointele;
                        if ($north === null){
                            $north = $pointlat;
                            $south = $pointlat;
                            $east = $pointlon;
                            $west = $pointlon;
                        }
                        array_push($shortPointList, Array($pointlat, $pointlon));
                        $lastShortPoint = $point;
                    }

                    if ($lastShortPoint !== null){
                        // if the point is more than 500m far from the last in shortPointList
                        // we add it
                        if (distance($lastShortPoint, $point) > $DISTANCE_BETWEEN_SHORT_POINTS){
                            array_push($shortPointList, Array($pointlat, $pointlon));
                            $lastShortPoint = $point;
                        }
                    }
                    if ($pointlat > $north){
                        $north = $pointlat;
                    }
                    if ($pointlat < $south){
                        $south = $pointlat;
                    }
                    if ($pointlon > $east){
                        $east = $pointlon;
                    }
                    if ($pointlon < $west){
                        $west = $pointlon;
                    }
                    if ($pointele !== null and ($min_elevation === null or $pointele < $min_elevation)){
                        $min_elevation = $pointele;
                    }
                    if ($pointele !== null and ($max_elevation === null or $pointele > $max_elevation)){
                        $max_elevation = $pointele;
                    }
                    if ($lastPoint !== null and $pointtime !== null and $lastTime !== null){
                        $t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

                        $speed = 0;
                        if ($t > 0){
                            $speed = $distToLast / $t;
                            $speed = $speed / 1000;
                            $speed = $speed * 3600;
                            if ($speed > $max_speed){
                                $max_speed = $speed;
                            }
                        }

                        if ($speed <= $STOPPED_SPEED_THRESHOLD){
                            $stopped_time += $t;
                            $stopped_distance += $distToLast;
                        }
                        else{
                            $moving_time += $t;
                            $moving_distance += $distToLast;
                        }
                    }
                    if ($lastPoint !== null){
                        $total_distance += $distToLast;
                    }
                    if ($lastPoint !== null and $pointele !== null and (!empty($lastPoint->ele))){
                        $deniv = $pointele - (float)$lastPoint->ele;
                    }
                    if ($lastDeniv !== null and $pointele !== null and $lastPoint !== null and (!empty($lastPoint->ele))){
                        // we start to go up
                        if ($isGoingUp === False and $deniv > 0){
                            $upBegin = (float)$lastPoint->ele;
                            $isGoingUp = True;
                            $neg_elevation += ($downBegin - (float)$lastPoint->ele);
                        }
                        if ($isGoingUp === True and $deniv < 0){
                            // we add the up portion
                            $pos_elevation += ((float)$lastPointele - $upBegin);
                            $isGoingUp = False;
                            $downBegin = (float)$lastPoint->ele;
                        }
                    }
                    // update vars
                    if ($lastPoint !== null and $pointele !== null and (!empty($lastPoint->ele))){
                        $lastDeniv = $deniv;
                    }

                    $lastPoint = $point;
                    $pointIndex += 1;
                }
            }

            if ($lastTime !== null and ($date_end === null or $lastTime > $date_end)){
                $date_end = $lastTime;
            }
        }

        # ROUTES
        foreach($gpx->rte as $route){
            $routename = $route->name;
            if (empty($routename)){
                $routename = '';
            }
            $trackNameList .= sprintf('"%s",', $routename);

            $lastPoint = null;
            $lastTime = null;
            $pointIndex = 0;
            $lastDeniv = null;
            foreach($route->rtept as $point){
                if (empty($point->ele)){
                    $pointele = null;
                }
                else{
                    $pointele = (float)$point->ele;
                }
                if (empty($point->time)){
                    $pointtime = null;
                }
                else{
                    $pointtime = new \DateTime($point->time);
                }
                if ($lastPoint !== null and (!empty($lastPoint->ele))){
                    $lastPointele = (float)$lastPoint->ele;
                }
                else{
                    $lastPointele = null;
                }
                if ($lastPoint !== null and (!empty($lastPoint->time))){
                    $lastTime = new \DateTime($lastPoint->time);
                }
                else{
                    $lastTime = null;
                }
                if ($lastPoint !== null){
                    $distToLast = distance($lastPoint, $point);
                }
                else{
                    $distToLast = null;
                }
                $pointlat = (float)$point['lat'];
                $pointlon = (float)$point['lon'];
                if ($pointIndex === 0){
                    if ($lat === '0' and $lon === '0'){
                        $lat = $pointlat;
                        $lon = $pointlon;
                    }
                    if ($pointtime !== null and ($date_begin === null or $pointtime < $date_begin)){
                        $date_begin = $pointtime;
                    }
                    $downBegin = $pointele;
                    if ($north === null){
                        $north = $pointlat;
                        $south = $pointlat;
                        $east = $pointlon;
                        $west = $pointlon;
                    }
                    array_push($shortPointList, Array($pointlat, $pointlon));
                    $lastShortPoint = $point;
                }

                if ($lastShortPoint !== null){
                    // if the point is more than 500m far from the last in shortPointList
                    // we add it
                    if (distance($lastShortPoint, $point) > $DISTANCE_BETWEEN_SHORT_POINTS){
                        array_push($shortPointList, Array($pointlat, $pointlon));
                        $lastShortPoint = $point;
                    }
                }
                if ($pointlat > $north){
                    $north = $pointlat;
                }
                if ($pointlat < $south){
                    $south = $pointlat;
                }
                if ($pointlon > $east){
                    $east = $pointlon;
                }
                if ($pointlon < $west){
                    $west = $pointlon;
                }
                if ($pointele !== null and ($min_elevation === null or $pointele < $min_elevation)){
                    $min_elevation = $pointele;
                }
                if ($pointele !== null and ($max_elevation === null or $pointele > $max_elevation)){
                    $max_elevation = $pointele;
                }
                if ($lastPoint !== null and $pointtime !== null and $lastTime !== null){
                    $t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

                    $speed = 0;
                    if ($t > 0){
                        $speed = $distToLast / $t;
                        $speed = $speed / 1000;
                        $speed = $speed * 3600;
                        if ($speed > $max_speed){
                            $max_speed = $speed;
                        }
                    }

                    if ($speed <= $STOPPED_SPEED_THRESHOLD){
                        $stopped_time += $t;
                        $stopped_distance += $distToLast;
                    }
                    else{
                        $moving_time += $t;
                        $moving_distance += $distToLast;
                    }
                }
                if ($lastPoint !== null){
                    $total_distance += $distToLast;
                }
                if ($lastPoint !== null and $pointele !== null and (!empty($lastPoint->ele))){
                    $deniv = $pointele - (float)$lastPoint->ele;
                }
                if ($lastDeniv !== null and $pointele !== null and $lastPoint !== null and (!empty($lastPoint->ele))){
                    // we start to go up
                    if ($isGoingUp === False and $deniv > 0){
                        $upBegin = (float)$lastPoint->ele;
                        $isGoingUp = True;
                        $neg_elevation += ($downBegin - (float)$lastPoint->ele);
                    }
                    if ($isGoingUp === True and $deniv < 0){
                        // we add the up portion
                        $pos_elevation += ((float)$lastPointele - $upBegin);
                        $isGoingUp = False;
                        $downBegin = (float)$lastPoint->ele;
                    }
                }
                // update vars
                if ($lastPoint !== null and $pointele !== null and (!empty($lastPoint->ele))){
                    $lastDeniv = $deniv;
                }

                $lastPoint = $point;
                $pointIndex += 1;
            }

            if ($lastTime !== null and ($date_end === null or $lastTime > $date_end)){
                $date_end = $lastTime;
            }
        }

        # TOTAL STATS : duration, avg speed, avg_moving_speed
        if ($date_end !== null and $date_begin !== null){
            $totsec = abs($date_end->getTimestamp() - $date_begin->getTimestamp());
            $total_duration = sprintf('%02d:%02d:%02d', (int)($totsec/3600), (int)(($totsec % 3600)/60), $totsec % 60); 
            if ($totsec === 0){
                $avg_speed = 0;
            }
            else{
                $avg_speed = $total_distance / $totsec;
                $avg_speed = $avg_speed / 1000;
                $avg_speed = $avg_speed * 3600;
                $avg_speed = sprintf('%.2f', $avg_speed);
            }
        }
        else{
            $total_duration = "???";
        }

        // determination of real moving average speed from moving time
        $moving_avg_speed = 0;
        if ($moving_time > 0){
            $moving_avg_speed = $total_distance / $moving_time;
            $moving_avg_speed = $moving_avg_speed / 1000;
            $moving_avg_speed = $moving_avg_speed * 3600;
            $moving_avg_speed = sprintf('%.2f', $moving_avg_speed);
        }

        # WAYPOINTS
        foreach($gpx->wpt as $waypoint){
            array_push($shortPointList, Array($waypoint['lat'], $waypoint['lon']));

            $waypointlat = (float)$waypoint['lat'];
            $waypointlon = (float)$waypoint['lon'];

            if ($lat === '0' and $lon === '0'){
                $lat = $waypointlat;
                $lon = $waypointlon;
            }

            if ($north === null or $waypointlat > $north){
                $north = $waypointlat;
            }
            if ($south === null or $waypointlat < $south){
                $south = $waypointlat;
            }
            if ($east === null or $waypointlon > $east){
                $east = $waypointlon;
            }
            if ($west === null or $waypointlon < $west){
                $west = $waypointlon;
            }
        }

        $trackNameList = trim($trackNameList, ',').']';
        if ($date_begin === null){
            $date_begin = '';
        }
        else{
            $date_begin = $date_begin->format('Y-m-d H:i:s');
        }
        if ($date_end === null){
            $date_end = '';
        }
        else{
            $date_end = $date_end->format('Y-m-d H:i:s');
        }
        $shortPointListTxt = '';
        foreach($shortPointList as $sp){
            $shortPointListTxt .= sprintf('[%s, %s],', $sp[0], $sp[1]);
        }
        $shortPointListTxt = '[ '.trim($shortPointListTxt, ',').' ]';
        if ($north === null){
            $north = 0;
        }
        if ($south === null){
            $south = 0;
        }
        if ($east === null){
            $east = 0;
        }
        if ($west === null){
            $west = 0;
        }

        if ($max_elevation === null){
            $max_elevation = '"???"';
        }
        else{
            $max_elevation = number_format($max_elevation, 2, '.', '');
        }
        if ($min_elevation === null){
            $min_elevation = '"???"';
        }
        else{
            $min_elevation = number_format($min_elevation, 2, '.', '');
        }
        $pos_elevation = number_format($pos_elevation, 2, '.', '');
        $neg_elevation = number_format($neg_elevation, 2, '.', '');
        
        $result = sprintf('[%s, %s, "%s", %.3f, "%s", "%s", "%s", %s, %.2f, %s, %s, %s, %.2f, "%s", "%s", %s, %d, %d, %d, %d, %s, %s]',
            $lat,
            $lon,
            $name,
            $total_distance,
            $total_duration,
            $date_begin,
            $date_end,
            $pos_elevation,
            $neg_elevation,
            $min_elevation,
            $max_elevation,
            $max_speed,
            $avg_speed,
            format_time_seconds($moving_time),
            format_time_seconds($stopped_time),
            $moving_avg_speed,
            $north,
            $south,
            $east,
            $west,
            $shortPointListTxt,
            $trackNameList
        );
        return $result;
    }

    /*
     * get marker string for each gpx file in the given tempdir
     * return an array indexed by trackname
     */
    private function getMarkersFromFiles($clear_path_to_process){
        $tmpgpxsmin = globRecursive($clear_path_to_process, '*.gpx', False);
        $tmpgpxsmaj = globRecursive($clear_path_to_process, '*.GPX', False);
        $tmpgpxs = array_merge($tmpgpxsmin, $tmpgpxsmaj);
        $result = Array();
        foreach ($tmpgpxs as $tmpgpx){
            $markerJson = $this->getMarkerFromFile($tmpgpx);
            if ($markerJson !== null){
                $result[basename($tmpgpx)] = $markerJson;
            }
        }
        return $result;
    }

    /**
     * Ajax markers json retrieval from DB
     *
     * First convert kml, tcx... files if necessary.
     * Then copy files to a temporary directory (decrypt them if necessary).
     * Then correct elevations if it was asked.
     * Then process the files to produce .geojson* and .marker content.
     * Then INSERT or UPDATE the database with processed data.
     * Then get the markers for all gpx files in the target folder
     * Then clean useless database entries (for files that no longer exist)
     *
     * @NoAdminRequired
     */
    public function getmarkers($subfolder, $scantype){
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();
        $subfolder_path = $userFolder->get($subfolder)->getPath();

        $subfolder = str_replace(array('../', '..\\'), '',  $subfolder);

        // make temporary dir to process decrypted files
        $tempdir = sys_get_temp_dir() . '/gpxpod' . rand() . '.tmp';
        if (! mkdir($tempdir)) {
            $response = new DataResponse(
                [
                    'markers'=>null,
                    'pictures'=>null,
                    'error'=>'Impossible to create temporary directory on server'
                ]
            );
            $csp = new ContentSecurityPolicy();
            $csp->addAllowedImageDomain('*')
                ->addAllowedMediaDomain('*')
                ->addAllowedConnectDomain('*');
            $response->setContentSecurityPolicy($csp);
            return $response;
        }

        // Convert KML to GPX
        // only if we want to display a folder AND it exists AND we want
        // to compute AND we find GPSBABEL AND file was not already converted

        if ($subfolder === '/'){
            $subfolder = '';
        }

        $filesByExtension = Array();
        foreach($this->extensions as $ext => $gpsbabel_fmt){
            $filesByExtension[$ext] = Array();

            foreach ($userFolder->get($subfolder)->search($ext) as $ff){
                if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                    dirname($ff->getPath()) === $subfolder_path and
                    (endswith($ff->getName(), $ext) or endswith($ff->getName(), strtoupper($ext)))
                ){
                    array_push($filesByExtension[$ext], $ff);
                }
            }
        }

        // convert kml, tcx etc...
        if ($userFolder->nodeExists($subfolder) and
        $userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER){
            $gpsbabel_path = getProgramPath('gpsbabel');

            if ($gpsbabel_path !== null){
                foreach($this->extensions as $ext => $gpsbabel_fmt){
                    if ($ext !== '.gpx'){
                        foreach($filesByExtension[$ext] as $f){
                            $name = $f->getName();
                            $gpx_targetname = str_replace($ext, '.gpx', $name);
                            $gpx_targetname = str_replace(strtoupper($ext), '.gpx', $gpx_targetname);
                            if (! $userFolder->nodeExists($subfolder.'/'.$gpx_targetname)){
                                // we read content, then write it in the tempdir
                                // then convert, then read content then write it back in
                                // the real dir

                                $content = $f->getContent();
                                $clear_path = $tempdir.'/'.$name;
                                $gpx_target_clear_path = $tempdir.'/'.$gpx_targetname;
                                file_put_contents($clear_path, $content);

                                $args = Array('-i', $gpsbabel_fmt, '-f', $clear_path, '-o',
                                    'gpx', '-F', $gpx_target_clear_path);
                                $cmdparams = '';
                                foreach($args as $arg){
                                    $shella = escapeshellarg($arg);
                                    $cmdparams .= " $shella";
                                }
                                exec(
                                    $gpsbabel_path.' '.$cmdparams,
                                    $output, $returnvar
                                );
                                $gpx_clear_content = file_get_contents($gpx_target_clear_path);
                                $gpx_file = $userFolder->newFile($subfolder.'/'.$gpx_targetname);
                                $gpx_file->putContent($gpx_clear_content);
                            }
                        }
                    }
                }
            }
        }

        // PROCESS gpx files and fill DB

        if ($userFolder->nodeExists($subfolder) and
            $userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER){

            // find gpxs db style
            $sqlgpx = 'SELECT trackpath FROM *PREFIX*gpxpod_tracks ';
            $sqlgpx .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'; ';
            $req = $this->dbconnection->prepare($sqlgpx);
            $req->execute();
            $gpxs_in_db = Array();
            while ($row = $req->fetch()){
                array_push($gpxs_in_db, $row['trackpath']);
            }
            $req->closeCursor();


            // find gpxs
            $gpxfiles = Array();
            foreach ($userFolder->get($subfolder)->search(".gpx") as $ff){
                if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                    dirname($ff->getPath()) === $subfolder_path and
                    (
                        endswith($ff->getName(), '.gpx') or
                        endswith($ff->getName(), '.GPX')
                    )
                ){
                    array_push($gpxfiles, $ff);
                }
            }

            $processtype_arg = 'newonly';
            if ($scantype === 'all' or $scantype === 'srtm' or $scantype === 'srtms'){
                $processtype_arg = 'all';
                $gpxs_to_process = $gpxfiles;
            }
            else{
                $gpxs_to_process = Array();
                foreach($gpxfiles as $gg){
                    $gpx_relative_path = str_replace($userfolder_path, '', $gg->getPath());
                    $gpx_relative_path = rtrim($gpx_relative_path, '/');
                    $gpx_relative_path = str_replace('//', '/', $gpx_relative_path);
                    //if (! $userFolder->nodeExists($gpx_relative_path.".geojson")){
                    if (! in_array($gpx_relative_path, $gpxs_in_db)){
                        // not in DB
                        array_push($gpxs_to_process, $gg);
                    }
                }
            }
            // copy files to tmpdir
            foreach($gpxs_to_process as $gpxfile){
                $gpxcontent = $gpxfile->getContent();
                $gpx_clear_path = $tempdir.'/'.$gpxfile->getName();
                file_put_contents($gpx_clear_path, $gpxcontent);
            }

            $clear_path_to_process = $tempdir.'/';

            // we correct elevations if it was asked :
            $gpxelePath = getProgramPath('gpxelevations');
            if (    $gpxelePath !== null
                and (    $scantype === 'srtm'
                      or $scantype === 'srtms'
                      or $scantype === 'newsrtm'
                      or $scantype === 'newsrtms'
                    )
                and count($gpxs_to_process) > 0
            ){
                $tmpgpxsmin = globRecursive($tempdir, '*.gpx', False);
                $tmpgpxsmaj = globRecursive($tempdir, '*.GPX', False);
                $tmpgpxs = array_merge($tmpgpxsmin, $tmpgpxsmaj);
                $args = Array();
                foreach($tmpgpxs as $tmpgpx){
                    if (!endswith($tmpgpx, '_corrected.gpx')){
                        array_push($args, $tmpgpx);
                    }
                }

                if ($scantype === 'srtms' or $scantype === 'newsrtms'){
                    array_push($args, '-s');
                }
                array_push($args, '-o');
                $cmdparams = '';
                foreach($args as $arg){
                    $shella = escapeshellarg($arg);
                    $cmdparams .= " $shella";
                }
                // srtm.py (used by gpxelevations) needs HOME or HOMEPATH
                // to be set to store cache data
                exec('export HOMEPATH="'.$tempdir.'"; '.
                    $gpxelePath.' '.$cmdparams,
                    $output, $returnvar
                );

                // create of update file
                $subfolderobj = $userFolder->get($subfolder);
                if ($returnvar === 0){
                    foreach($tmpgpxs as $tmpgpx){
                        $correctedPath = str_replace(Array('.gpx', '.GPX'), '_with_elevations.gpx', $tmpgpx);
                        $correctedRenamedPath = str_replace(Array('.gpx', '.GPX'), '_corrected.gpx', $tmpgpx);
                        if (file_exists($correctedPath)){
                            rename($correctedPath, $correctedRenamedPath);
                            $ofname = basename($correctedRenamedPath);
                            $ofpath = $subfolder.'/'.$ofname;
                            if ($userFolder->nodeExists($ofpath)){
                                $of = $userFolder->get($ofpath);
                                if ($of->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                                    $of->isUpdateable()){
                                    $of->putContent(file_get_contents($correctedRenamedPath));
                                }
                            }
                            else{
                                if ($subfolderobj->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                                        $subfolderobj->isCreatable()){
                                    $subfolderobj->newFile($ofname);
                                    $subfolderobj->get($ofname)->putContent(file_get_contents($correctedRenamedPath));
                                }
                            }
                        }
                    }
                }
            }

            $markers = $this->getMarkersFromFiles($clear_path_to_process);

            // DB STYLE
            foreach($markers as $trackname => $marker){
                if (file_exists($tempdir.'/'.$trackname)){
                    $gpx_relative_path = $subfolder.'/'.$trackname;

                    if (! in_array($gpx_relative_path, $gpxs_in_db)){
                        try{
                            $sql = 'INSERT INTO *PREFIX*gpxpod_tracks';
                            $sql .= ' ('.$this->dbdblquotes.'user'.$this->dbdblquotes.',trackpath,marker) ';
                            $sql .= 'VALUES ('.$this->db_quote_escape_string($this->userId).',';
                            $sql .= $this->db_quote_escape_string($gpx_relative_path).',';
                            $sql .= $this->db_quote_escape_string($marker).');';
                            $req = $this->dbconnection->prepare($sql);
                            $req->execute();
                            $req->closeCursor();
                        }
                        catch (\Exception $e) {
                            error_log("Exception in Owncloud : ".$e->getMessage());
                        }
                    }
                    else{
                        try{
                            $sqlupd = 'UPDATE *PREFIX*gpxpod_tracks ';
                            $sqlupd .= 'SET marker='.$this->db_quote_escape_string($marker).' ';
                            $sqlupd .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=';
                            $sqlupd .= $this->db_quote_escape_string($this->userId).' AND ';
                            $sqlupd .= 'trackpath='.$this->db_quote_escape_string($gpx_relative_path).'; ';
                            $req = $this->dbconnection->prepare($sqlupd);
                            $req->execute();
                            $req->closeCursor();
                        }
                        catch (\Exception $e) {
                            error_log("Exception in Owncloud : ".$e->getMessage());
                        }
                    }
                }
            }
            // delete tmpdir
            delTree($tempdir);
        }
        else{
        }

        // PROCESS error management

        // info for JS

        // build markers
        $subfolder_sql = $subfolder;
        if ($subfolder === ''){
            $subfolder_sql = '/';
        }
        $markertxt = '{"markers" : [';
        // DB style
        $sqlmar = 'SELECT trackpath, marker FROM *PREFIX*gpxpod_tracks ';
        $sqlmar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        // TODO maybe remove the LIKE and just use the php filtering that is following
        // and enough
        $sqlmar .= 'AND trackpath LIKE '.$this->db_quote_escape_string($subfolder_sql.'%').'; ';
        $req = $this->dbconnection->prepare($sqlmar);
        $req->execute();
        while ($row = $req->fetch()){
            if (dirname($row['trackpath']) === $subfolder_sql){
                // if the gpx file exists
                if ($userFolder->nodeExists($row['trackpath']) and
                    $userFolder->get($row['trackpath'])->getType() === \OCP\Files\FileInfo::TYPE_FILE){
                    $markertxt .= $row['marker'];
                    $markertxt .= ',';
                }
            }
        }
        $req->closeCursor();

        // CLEANUP DB for non-existing files
        $this->cleanDbFromAbsentFiles($subfolder);

        $markertxt = rtrim($markertxt, ',');
        $markertxt .= ']}';

        $pictures_json_txt = $this->getGeoPicsFromFolder($subfolder);

        $response = new DataResponse(
            [
                'markers'=>$markertxt,
                'pictures'=>$pictures_json_txt,
                'error'=>''
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * Method to ask elevation correction on a single track.
     * gpxelevations (from SRTM.py) is called to do so in a temporary directory
     * then, the result track file is processed to
     * finally update the DB
     * @NoAdminRequired
     */
    public function processTrackElevations($trackname, $folder, $smooth) {
        $userFolder = \OC::$server->getUserFolder();
        $gpxelePath = getProgramPath('gpxelevations');
        $success = False;
        $message = '';

        $filerelpath = $folder.'/'.$trackname;

        if ($userFolder->nodeExists($filerelpath) and
            $userFolder->get($filerelpath)->getType() === \OCP\Files\FileInfo::TYPE_FILE and
            $gpxelePath !== null
        ){
            $tempdir = sys_get_temp_dir() . '/gpxpod' . rand() . '.tmp';
            mkdir($tempdir);

            $gpxfile = $userFolder->get($filerelpath);
            $gpxcontent = $gpxfile->getContent();
            $gpx_clear_path = $tempdir.'/'.$gpxfile->getName();
            file_put_contents($gpx_clear_path, $gpxcontent);

            // srtmification
            $args = Array();
            array_push($args, $gpx_clear_path);

            if ($smooth === 'true'){
                array_push($args, '-s');
            }
            array_push($args, '-o');
            $cmdparams = '';
            foreach($args as $arg){
                $shella = escapeshellarg($arg);
                $cmdparams .= " $shella";
            }
            // srtm.py (used by gpxelevations) needs HOME or HOMEPATH
            // to be set to store cache data
            exec('export HOMEPATH="'.$tempdir.'"; '.
                $gpxelePath.' '.$cmdparams,
                $output, $returnvar
            );

            $subfolderobj = $userFolder->get($folder);
            // overwrite original gpx files with corrected ones
            if ($returnvar === 0){
                $correctedPath = str_replace(Array('.gpx', '.GPX'), '_with_elevations.gpx', $gpx_clear_path);
                $correctedRenamedPath = str_replace(Array('.gpx', '.GPX'), '_corrected.gpx', $gpx_clear_path);
                if (file_exists($correctedPath)){
                    rename($correctedPath, $correctedRenamedPath);
                    $ofname = basename($correctedRenamedPath);
                    $ofpath = $folder.'/'.$ofname;
                    if ($userFolder->nodeExists($ofpath)){
                        $of = $userFolder->get($ofpath);
                        if ($of->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                            $of->isUpdateable()){
                            $of->putContent(file_get_contents($correctedRenamedPath));
                        }
                    }
                    else{
                        if ($subfolderobj->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                            $subfolderobj->isCreatable()){
                            $subfolderobj->newFile($ofname);
                            $subfolderobj->get($ofname)->putContent(file_get_contents($correctedRenamedPath));
                        }
                    }
                }
            }
            else{
                $message = 'There was an error during "gpxelevations" execution on the server';
            }

            // PROCESS

            if ($returnvar === 0){
                $mar_content = $this->getMarkerFromFile($correctedRenamedPath);
            }

            $cleanFolder = $folder;
            if ($folder === '/'){
                $cleanFolder = '';
            }
            // in case it does not exists, the following query won't have any effect
            if ($returnvar === 0){
                $gpx_relative_path = $cleanFolder.'/'.basename($correctedRenamedPath);
                try{
                    $sqlupd = 'UPDATE *PREFIX*gpxpod_tracks ';
                    $sqlupd .= 'SET marker='.$this->db_quote_escape_string($mar_content).' ';
                    $sqlupd .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=';
                    $sqlupd .= $this->db_quote_escape_string($this->userId).' AND ';
                    $sqlupd .= 'trackpath='.$this->db_quote_escape_string($gpx_relative_path).'; ';
                    $req = $this->dbconnection->prepare($sqlupd);
                    $req->execute();
                    $req->closeCursor();
                    $success = True;
                }
                catch (\Exception $e) {
                    error_log('Exception in Owncloud : '.$e->getMessage());
                }
            }

            // delete tmpdir
            delTree($tempdir);
        }

        $response = new DataResponse(
            [
                'done'=>$success,
                'message'=>$message
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * get list of geolocated pictures in $subfolder with coordinates
     * first copy the pics to a temp dir
     * then get the pic list and coords with gpsbabel
     */
    private function getGeoPicsFromFolder($subfolder, $user=""){
        $pictures_json_txt = '{';

        // if user is not given, the request comes from connected user threw getmarkers
        if ($user === ""){
            $userFolder = \OC::$server->getUserFolder();
        }
        // else, it comes from a public dir
        else{
            $userFolder = \OC::$server->getUserFolder($user);
        }
        $subfolder = str_replace(array('../', '..\\'), '',  $subfolder);
        $subfolder_path = $userFolder->get($subfolder)->getPath();

        // make temporary dir to process decrypted files
        $tempdir = sys_get_temp_dir() . '/gpxpod' . rand() . '.tmp';
        mkdir($tempdir);

        // find pictures
        $picfiles = Array();
        foreach ($userFolder->get($subfolder)->search(".jpg") as $ff){
            if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                (
                    endswith($ff->getName(), '.jpg') or
                    endswith($ff->getName(), '.JPG')
                )
            ){
                array_push($picfiles, $ff);
            }
        }

        // copy picture files to tmpdir
        foreach($picfiles as $picfile){
            $piccontent = $picfile->getContent();
            $pic_clear_path = $tempdir.'/'.$picfile->getName();
            file_put_contents($pic_clear_path, $piccontent);

            try {
                $exif = exif_read_data($pic_clear_path, 0, true);
                if (    isset($exif['GPS'])
                    and isset($exif['GPS']['GPSLongitude'])
                    and isset($exif['GPS']['GPSLatitude'])
                    and isset($exif['GPS']['GPSLatitudeRef'])
                    and isset($exif['GPS']['GPSLongitudeRef'])
                ){
                    $lon = getDecimalCoords($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
                    $lat = getDecimalCoords($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
                    $pictures_json_txt .= '"'.$picfile->getName().'": ['.$lat.', '.$lon.'],';
                }
            }
            catch (\Exception $e) {
                error_log(e);
            }
        }

        $pictures_json_txt = rtrim($pictures_json_txt, ',').'}';

        delTree($tempdir);

        return $pictures_json_txt;
    }

    /**
     * delete from DB all entries refering to absent files
     * optionnal parameter : folder to clean
     */
    private function cleanDbFromAbsentFiles($subfolder) {
        $subfo = $subfolder;
        if ($subfolder === ''){
            $subfo = '/';
        }
        $userFolder = \OC::$server->getUserFolder();
        $gpx_paths_to_del = Array();

        $sqlmar = 'SELECT trackpath FROM *PREFIX*gpxpod_tracks ';
        $sqlmar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'; ';
        $req = $this->dbconnection->prepare($sqlmar);
        $req->execute();
        while ($row = $req->fetch()){
            if (dirname($row['trackpath']) === $subfo or $subfo === null){
                // delete DB entry if the file does not exist
                if (
                    (! $userFolder->nodeExists($row['trackpath'])) or
                    $userFolder->get($row['trackpath'])->getType() !== \OCP\Files\FileInfo::TYPE_FILE){
                    array_push($gpx_paths_to_del, $this->db_quote_escape_string($row['trackpath']));
                }
            }
        }

        if (count($gpx_paths_to_del) > 0){
            $sqldel = 'DELETE FROM *PREFIX*gpxpod_tracks ';
            $sqldel .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' AND (trackpath=';
            $sqldel .= implode(' OR trackpath=', $gpx_paths_to_del);
            $sqldel .= ');';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
        }
    }

    /**
     * method to get the URL to download a public file with OC/NC File system
     * from the file object and the user who shares the file
     *
     * @return null if the file is not shared or inside a shared folder
     */
    private function getPublinkDownloadURL($file, $username){
        $uf = \OC::$server->getUserFolder($username);
        $dl_url = null;

        // CHECK if file is shared
        $shares = $this->shareManager->getSharesBy($username,
            \OCP\Share::SHARE_TYPE_LINK, $file, false, 1, 0);
        if (count($shares) > 0){
            foreach($shares as $share){
                if ($share->getPassword() === null){
                    $dl_url = $share->getToken();
                    break;
                }
            }
        }

        if ($dl_url === null){
            // CHECK if file is inside a shared folder
            $tmpfolder = $file->getParent();
            while ($tmpfolder->getPath() !== $uf->getPath() and
                $tmpfolder->getPath() !== "/" and $dl_url === null){
                $shares_folder = $this->shareManager->getSharesBy($username,
                    \OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
                if (count($shares_folder) > 0){
                    foreach($shares_folder as $share){
                        if ($share->getPassword() === null){
                            // one folder above the file is shared without passwd
                            $token = $share->getToken();
                            $subpath = str_replace($tmpfolder->getPath(), '', $file->getPath());
                            $dl_url = $token.'/download?path='.rtrim(dirname($subpath), '/');
                            $dl_url .= '&files='.basename($subpath);

                            break;
                        }
                    }
                }
                $tmpfolder = $tmpfolder->getParent();
            }
        }

        return $dl_url;
    }

    /**
     * @return null if the file is not shared or inside a shared folder
     */
    private function getPublinkParameters($file, $username){
        $uf = \OC::$server->getUserFolder($username);
        $paramArray = null;

        // CHECK if file is shared
        $shares = $this->shareManager->getSharesBy($username,
            \OCP\Share::SHARE_TYPE_LINK, $file, false, 1, 0);
        if (count($shares) > 0){
            foreach($shares as $share){
                if ($share->getPassword() === null){
                    $paramArray = Array('token'=>$share->getToken(), 'path'=>'', 'filename'=>'');
                    break;
                }
            }
        }

        if ($paramArray === null){
            // CHECK if file is inside a shared folder
            $tmpfolder = $file->getParent();
            while ($tmpfolder->getPath() !== $uf->getPath() and
                $tmpfolder->getPath() !== "/" and $paramArray === null){
                $shares_folder = $this->shareManager->getSharesBy($username,
                    \OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
                if (count($shares_folder) > 0){
                    foreach($shares_folder as $share){
                        if ($share->getPassword() === null){
                            // one folder above the file is shared without passwd
                            $token = $share->getToken();
                            $subpath = str_replace($tmpfolder->getPath(), '', $file->getPath());
                            $filename = basename($subpath);
                            $subpath = dirname($subpath);
                            if ($subpath !== '/'){
                                $subpath = rtrim($subpath, '/');
                            }
                            $paramArray = Array(
                                'token'=>$token,
                                'path'=>$subpath,
                                'filename'=>$filename
                            );
                            break;
                        }
                    }
                }
                $tmpfolder = $tmpfolder->getParent();
            }
        }

        return $paramArray;
    }

    /**
     * Handle public link view request
     * [Deprecated] kept for link retro compat
     *
     * Check if target file is shared by public link
     * or if one of its parent directories is shared by public link.
     * Then directly provide all data to the view
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function publink() {
        if (!empty($_GET)){
            $dbconnection = \OC::$server->getDatabaseConnection();
            $user = $_GET['user'];
            $path = $_GET['filepath'];
            $uf = \OC::$server->getUserFolder($user);

            $dl_url = null;

            if ($uf->nodeExists($path)){
                $thefile = $uf->get($path);

                $dl_url = $this->getPublinkDownloadURL($thefile, $user);

                if ($dl_url !== null){
                    // gpx exists and is shared with no password
                    $sqlgeomar = 'SELECT marker FROM *PREFIX*gpxpod_tracks ';
                    $sqlgeomar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).' ';
                    $sqlgeomar .= 'AND trackpath='.$this->db_quote_escape_string($path).' ';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $markercontent = $row['marker'];
                        break;
                    }
                    $req->closeCursor();

                    $gpxContent = $thefile->getContent();

                }
                else{
                    return 'This file is not a public share';
                }
            }
            else{
                return 'This file is not a public share';
            }
        }

        $extraSymbolList = $this->getExtraSymbolList();
        $gpxedit_version = $this->config->getAppValue('gpxedit', 'installed_version');

        // PARAMS to send to template

        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>'',
            'extra_scan_type'=>Array(),
            'tileservers'=>Array(),
            'publicgpx'=>$gpxContent,
            'publicmarker'=>$markercontent,
            'publicdir'=>'',
            'pictures'=>'',
            'token'=>$dl_url,
            'extrasymbols'=>$extraSymbolList,
            'gpxedit_version'=>$gpxedit_version,
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            //->allowEvalScript('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * Handle public link
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function publicFile() {
        if (!empty($_GET)){
            $dbconnection = \OC::$server->getDatabaseConnection();
            $token = $_GET['token'];
            $path = '';
            $filename = '';
            if (isset($_GET['path'])){
                $path = $_GET['path'];
            }
            if (isset($_GET['filename'])){
                $filename = $_GET['filename'];
            }

            if ($path && $filename){
                if ($path !== '/'){
                    $dlpath = rtrim($path, '/');
                }
                else{
                    $dlpath = $path;
                }
                $dl_url = $token.'/download?path='.$dlpath;
                $dl_url .= '&files='.$filename;
            }
            else{
                $dl_url = $token.'/download';
            }

            $share = $this->shareManager->getShareByToken($token);
            $user = $share->getSharedBy();
            $passwd = $share->getPassword();
            $shareNode = $share->getNode();
            $nodeid = $shareNode->getId();
            $uf = \OC::$server->getUserFolder($user);

            if ($passwd === null){
                if ($path && $filename){
                    if ($shareNode->nodeExists($path.'/'.$filename)){
                        $theid = $shareNode->get($path.'/'.$filename)->getId();
                        // we get the node for the user who shared
                        // (the owner may be different if the file is shared from user to user)
                        $thefile = $uf->getById($theid)[0];
                    }
                    else{
                        return 'This file is not a public share';
                    }
                }
                else{
                    $thefile = $uf->getById($nodeid)[0];
                }

                if ($thefile->getType() === \OCP\Files\FileInfo::TYPE_FILE){
                    $userfolder_path = $uf->getPath();
                    $rel_file_path = str_replace($userfolder_path, '', $thefile->getPath());

                    $sqlgeomar = 'SELECT marker FROM *PREFIX*gpxpod_tracks ';
                    $sqlgeomar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).' ';
                    $sqlgeomar .= 'AND trackpath='.$this->db_quote_escape_string($rel_file_path).' ';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $markercontent = $row['marker'];
                        break;
                    }
                    $req->closeCursor();

                    $gpxContent = $thefile->getContent();

                }
                else{
                    return 'This file is not a public share';
                }
            }
            else{
                return 'This file is not a public share';
            }
        }

        $extraSymbolList = $this->getExtraSymbolList();
        $gpxedit_version = $this->config->getAppValue('gpxedit', 'installed_version');

        // PARAMS to send to template

        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>'',
            'extra_scan_type'=>Array(),
            'tileservers'=>Array(),
            'publicgpx'=>$gpxContent,
            'publicmarker'=>$markercontent,
            'publicdir'=>'',
            'pictures'=>'',
            'token'=>$dl_url,
            'extrasymbols'=>$extraSymbolList,
            'gpxedit_version'=>$gpxedit_version,
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            //->allowEvalScript('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function getPubfolderDownloadURL($dir, $username){
        $uf = \OC::$server->getUserFolder($username);
        $userfolder_path = $uf->getPath();
        $dl_url = null;

        // check that this is a directory
        if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER){
            $shares_folder = $this->shareManager->getSharesBy($username,
                \OCP\Share::SHARE_TYPE_LINK, $dir, false, 1, 0);
            // check that this directory is publicly shared
            if (count($shares_folder) > 0){
                foreach($shares_folder as $share){
                    if ($share->getPassword() === null){
                        // the directory is shared without passwd
                        $token = $share->getToken();
                        $dl_url = $token;
                        //$dl_url = $token.'/download?path=';
                        //$dl_url .= '&files=';
                        break;
                    }
                }
            }

            if ($dl_url === null){
                // CHECK if folder is inside a shared folder
                $tmpfolder = $dir->getParent();
                while ($tmpfolder->getPath() !== $uf->getPath() and
                    $tmpfolder->getPath() !== "/" and $dl_url === null){
                    $shares_folder = $this->shareManager->getSharesBy($username,
                        \OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
                    if (count($shares_folder) > 0){
                        foreach($shares_folder as $share){
                            if ($share->getPassword() === null){
                                // one folder above the dir is shared without passwd
                                $token = $share->getToken();
                                $subpath = str_replace($tmpfolder->getPath(), '', $dir->getPath());
                                $dl_url = $token.'?path='.rtrim($subpath, '/');

                                break;
                            }
                        }
                    }
                    $tmpfolder = $tmpfolder->getParent();
                }
            }
        }

        return $dl_url;
    }

    private function getPubfolderParameters($dir, $username){
        $uf = \OC::$server->getUserFolder($username);
        $userfolder_path = $uf->getPath();
        $paramArray = null;

        // check that this is a directory
        if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER){
            $shares_folder = $this->shareManager->getSharesBy($username,
                \OCP\Share::SHARE_TYPE_LINK, $dir, false, 1, 0);
            // check that this directory is publicly shared
            if (count($shares_folder) > 0){
                foreach($shares_folder as $share){
                    if ($share->getPassword() === null){
                        // the directory is shared without passwd
                        $paramArray = Array('token'=>$share->getToken(), 'path'=>'');
                        break;
                    }
                }
            }

            if ($paramArray === null){
                // CHECK if folder is inside a shared folder
                $tmpfolder = $dir->getParent();
                while ($tmpfolder->getPath() !== $uf->getPath() and
                    $tmpfolder->getPath() !== "/" and $paramArray === null){
                    $shares_folder = $this->shareManager->getSharesBy($username,
                        \OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
                    if (count($shares_folder) > 0){
                        foreach($shares_folder as $share){
                            if ($share->getPassword() === null){
                                // one folder above the dir is shared without passwd
                                $token = $share->getToken();
                                $subpath = str_replace($tmpfolder->getPath(), '', $dir->getPath());
                                if ($subpath !== '/'){
                                    $subpath = rtrim($subpath, '/');
                                }
                                $paramArray = Array('token'=>$share->getToken(), 'path'=>$subpath);
                                break;
                            }
                        }
                    }
                    $tmpfolder = $tmpfolder->getParent();
                }
            }
        }

        return $paramArray;
    }

    /**
     * Handle public directory link view request
     *
     * Check if target directory is shared by public link
     * Then directly provide all data to the view
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function pubdirlink() {
        if (!empty($_GET)){
            $dbconnection = \OC::$server->getDatabaseConnection();
            $user = $_GET['user'];
            $path = $_GET['dirpath'];
            $uf = \OC::$server->getUserFolder($user);
            $userfolder_path = $uf->getPath();

            $dl_url = null;

            if ($uf->nodeExists($path)){
                $thedir = $uf->get($path);

                $dl_url = $this->getPubfolderDownloadURL($thedir, $user);

                if ($dl_url !== null){
                    // get list of gpx in the directory
                    $gpxs = $thedir->search(".gpx");
                    $gpx_inside_thedir = Array();
                    foreach($gpxs as $file){
                        if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                            dirname($file->getPath()) === $thedir->getPath() and
                            (
                                endswith($file->getName(), '.gpx') or
                                endswith($file->getName(), '.GPX')
                            )
                        ){
                            $rel_file_path = str_replace($userfolder_path, '', $file->getPath());
                            array_push($gpx_inside_thedir, $this->db_quote_escape_string($rel_file_path));
                        }
                    }

                    // get the tracks data from DB
                    $sqlgeomar = 'SELECT trackpath, ';
                    $sqlgeomar .= 'marker FROM *PREFIX*gpxpod_tracks ';
                    $sqlgeomar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).' AND (';
                    $sqlgeomar .= 'trackpath=';
                    $sqlgeomar .= implode(' OR trackpath=', $gpx_inside_thedir);
                    $sqlgeomar .= ');';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    $markertxt = '{"markers" : [';
                    while ($row = $req->fetch()){
                        $trackname = basename($row['trackpath']);
                        $markertxt .= $row['marker'];
                        $markertxt .= ',';
                    }
                    $req->closeCursor();

                    $markertxt = rtrim($markertxt, ',');
                    $markertxt .= ']}';
                }
                else{
                    return "This directory is not a public share";
                }
            }
            else{
                return "This directory is not a public share";
            }
            $pictures_json_txt = $this->getGeoPicsFromFolder($path, $user);
        }

        $extraSymbolList = $this->getExtraSymbolList();
        $gpxedit_version = $this->config->getAppValue('gpxedit', 'installed_version');

        // PARAMS to send to template

        $rel_dir_path = str_replace($userfolder_path, '', $thedir->getPath());

        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>$user,
            'extra_scan_type'=>Array(),
            'tileservers'=>Array(),
            'publicgpx'=>'',
            'publicmarker'=>$markertxt,
            'publicdir'=>$rel_dir_path,
            'token'=>$dl_url,
            'pictures'=>$pictures_json_txt,
            'extrasymbols'=>$extraSymbolList,
            'gpxedit_version'=>$gpxedit_version,
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            //->allowEvalScript('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * Handle public directory link view request from share
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function publicFolder() {
        if (!empty($_GET)){
            $dbconnection = \OC::$server->getDatabaseConnection();
            $token = $_GET['token'];
            $path = '';
            if (isset($_GET['path'])){
                $path = $_GET['path'];
            }

            if ($path){
                $dl_url = $token.'?path='.$path;
            }
            else{
                $dl_url = $token.'?path=/';
            }

            $share = $this->shareManager->getShareByToken($token);
            $user = $share->getSharedBy();
            $passwd = $share->getPassword();
            $shareNode = $share->getNode();
            $nodeid = $shareNode->getId();
            $target = $share->getTarget();
            $uf = \OC::$server->getUserFolder($user);

            if ($passwd === null){
                if ($path){
                    if ($shareNode->nodeExists($path)){
                        $theid = $shareNode->get($path)->getId();
                        // we get the node for the user who shared
                        // (the owner may be different if the file is shared from user to user)
                        $thedir = $uf->getById($theid)[0];
                    }
                    else{
                        return "This directory is not a public share";
                    }
                }
                else{
                    $thedir = $uf->getById($nodeid)[0];
                }

                if ($thedir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER){
                    $userfolder_path = $uf->getPath();

                    $rel_dir_path = str_replace($userfolder_path, '', $thedir->getPath());
                    $rel_dir_path = rtrim($rel_dir_path, '/');

                    // get the tracks data from DB
                    $sqlgeomar = 'SELECT trackpath, ';
                    $sqlgeomar .= 'marker FROM *PREFIX*gpxpod_tracks ';
                    $sqlgeomar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).' AND ';
                    $sqlgeomar .= 'trackpath LIKE '.$this->db_quote_escape_string($rel_dir_path.'%').'; ';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    $markertxt = '{"markers" : [';
                    while ($row = $req->fetch()) {
                        if (dirname($row['trackpath']) === $rel_dir_path) {
                            $trackname = basename($row['trackpath']);
                            $markertxt .= $row['marker'];
                            $markertxt .= ',';
                        }
                    }
                    $req->closeCursor();

                    $markertxt = rtrim($markertxt, ',');
                    $markertxt .= ']}';
                }
                else{
                    return "This directory is not a public share";
                }
            }
            else{
                return "This directory is not a public share";
            }
            $pictures_json_txt = $this->getGeoPicsFromFolder($rel_dir_path, $user);
        }

        $extraSymbolList = $this->getExtraSymbolList();
        $gpxedit_version = $this->config->getAppValue('gpxedit', 'installed_version');

        // PARAMS to send to template

        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>$user,
            'extra_scan_type'=>Array(),
            'tileservers'=>Array(),
            'publicgpx'=>'',
            'publicmarker'=>$markertxt,
            'publicdir'=>$rel_dir_path,
            'token'=>$dl_url,
            'pictures'=>$pictures_json_txt,
            'extrasymbols'=>$extraSymbolList,
            'gpxedit_version'=>$gpxedit_version,
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedChildSrcDomain('*')
            ->addAllowedObjectDomain('*')
            ->addAllowedScriptDomain('*')
            //->allowEvalScript('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function isFileShareable($trackpath) {
        $uf = \OC::$server->getUserFolder($this->userId);
        $isIt = false;

        if ($uf->nodeExists($trackpath)){
            $thefile = $uf->get($trackpath);
            $publinkParameters = $this->getPublinkParameters($thefile, $this->userId);
            if ($publinkParameters !== null){
                $isIt = true;
            }
            else{
                $publinkParameters = Array('token'=>'','path'=>'','filename'=>'');
            }
        }

        $response = new DataResponse(
            [
                'response'=>$isIt,
                'token'=>$publinkParameters['token'],
                'path'=>$publinkParameters['path'],
                'filename'=>$publinkParameters['filename']
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function isFolderShareable($folderpath) {
        $uf = \OC::$server->getUserFolder($this->userId);
        $isIt = false;

        if ($uf->nodeExists($folderpath)){
            $thefolder = $uf->get($folderpath);
            $pubFolderParams = $this->getPubfolderParameters($thefolder, $this->userId);
            if ($pubFolderParams !== null){
                $isIt = true;
            }
            else{
                $pubFolderParams = Array('token'=>'','path'=>'');
            }
        }

        $response = new DataResponse(
            [
                'response'=>$isIt,
                'token'=>$pubFolderParams['token'],
                'path'=>$pubFolderParams['path']
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * @NoAdminRequired
     */
    public function deleteTracks($tracknames, $folder) {
        $uf = \OC::$server->getUserFolder($this->userId);
        $done = False;
        $deleted = '';
        $notdeleted = '';
        $message = '';
        $cleanFolder = str_replace(array('../', '..\\'), '',  $folder);

        if ($uf->nodeExists($cleanFolder)){
            $folderNode = $uf->get($cleanFolder);
            foreach ($tracknames as $name) {
                $cleanName = basename(str_replace(array('../', '..\\'), '',  $name));
                if ($folderNode->nodeExists($cleanName)){
                    $file = $folderNode->get($cleanName);
                    if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                        //$file->getPermissions() & \OCP\Constants::PERMISSION_DELETE) {
                        $file->isDeletable()
                    ) {
                        $file->delete();
                        $deleted .= $cleanName.', ';
                    }
                    else {
                        $notdeleted .= $cleanName.', ';
                    }
                }
            }
            $done = True;
        }
        else {
            $message = $folder . ' does not exist.';
        }

        $deleted = rtrim($deleted, ', ');
        $notdeleted = rtrim($notdeleted, ', ');

        $response = new DataResponse(
            [
                'message'=>$message,
                'deleted'=>$deleted,
                'notdeleted'=>$notdeleted,
                'done'=>$done
            ]
        );
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

}
