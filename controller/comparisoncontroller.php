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

class ComparisonController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $userAbsoluteDataPath;
    private $dbconnection;
    private $dbtype;
    private $appPath;

    public function __construct($AppName, IRequest $request, $UserId,
        $userfolder, $config, IAppManager $appManager){
        parent::__construct($AppName, $request);
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('gpxpod');
        }
        $this->userId = $UserId;
        $this->dbtype = $config->getSystemValue('dbtype');
        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '';
        }
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
            // IConfig object
            $this->config = $config;
            // absolute path to user files folder
            $this->userAbsoluteDataPath =
                $this->config->getSystemValue('datadirectory').
                rtrim($this->userfolder->getFullPath(''), '/');

            $this->dbconnection = \OC::$server->getDatabaseConnection();
        }
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    private function getUserTileServers($type){
        // custom tile servers management
        $sqlts = 'SELECT servername, url FROM *PREFIX*gpxpod_tile_servers ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlts .= 'AND type='.$this->db_quote_escape_string($type).';';
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
     * Do the comparison, receive GET parameters.
     * This method is called when asking comparison of two tracks from
     * owncloud filesystem.
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function gpxvcomp() {
        $userFolder = \OC::$server->getUserFolder();

        $gpxs = Array();

        $tempdir = sys_get_temp_dir() . '/gpxpod' . rand() . '.tmp';
        mkdir($tempdir);

        // gpx in GET parameters
        if (!empty($_GET)){
            $subfolder = str_replace(array('../', '..\\'), '', $_GET['subfolder']);
            for ($i=1; $i<=10; $i++){
                if (isset($_GET['name'.$i]) and $_GET['name'.$i] !== ""){
                    $name = str_replace(array('/', '\\'), '', $_GET['name'.$i]);

                    $file = $userFolder->get($subfolder.'/'.$name);
                    $content = $file->getContent();

                    file_put_contents($tempdir.'/'.$name, $content);
                    array_push($gpxs, $name);
                }
            }
        }

        $process_errors = Array();

        if (count($gpxs)>0){
            $geojson = $this->processTempDir($tempdir, $process_errors);
            $stats = $this->getStats($tempdir, $process_errors);
        }

        delTree($tempdir);

        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');

        // PARAMS to send to template

        require_once('tileservers.php');
        $params = [
            'error_output'=>$process_errors,
            'gpxs'=>$gpxs,
            'stats'=>$stats,
            'geojson'=>$geojson,
            'basetileservers'=>$baseTileServers,
            'tileservers'=>$tss,
            'overlayservers'=>$oss
        ];
        $response = new TemplateResponse('gpxpod', 'compare', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    /**
     * Compare tracks uploaded in POST data.
     * This method is called when user provided external files
     * in the comparison page form.
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function gpxvcompp() {
        $gpxs = Array();

        $tempdir = sys_get_temp_dir() . '/gpxpod' . rand() . '.tmp';
        mkdir($tempdir);

        // Get uploaded files and copy them in temp dir

        // we uploaded a gpx by the POST form
        if (!empty($_POST)){
            // we copy each gpx in the tempdir
            for ($i=1; $i<=10; $i++){
                if (isset($_FILES["gpx$i"]) and $_FILES["gpx$i"]['name'] !== ""){
                    $name = str_replace(" ","_",$_FILES["gpx$i"]['name']);
                    copy($_FILES["gpx$i"]['tmp_name'], "$tempdir/$name");
                    array_push($gpxs, $name);
                }
            }
        }

        // Process gpx files

        $process_errors = Array();

        if (count($gpxs)>0){
            $geojson = $this->processTempDir($tempdir, $process_errors);
            $stats = $this->getStats($tempdir, $process_errors);
        }

        delTree($tempdir);

        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');

        // PARAMS to send to template

        require_once('tileservers.php');
        $params = [
            'error_output'=>$process_errors,
            'gpxs'=>$gpxs,
            'stats'=>$stats,
            'geojson'=>$geojson,
            'basetileservers'=>$baseTileServers,
            'tileservers'=>$tss,
            'overlayservers'=>$oss
        ];
        $response = new TemplateResponse('gpxpod', 'compare', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function processTempDir($tempdir, &$process_errors){
        $paths = globRecursive($tempdir, '*.gpx', False);
        $contents = Array();
        $indexes = Array();
        $taggedGeo = Array();

        foreach ($paths as $p){
            $content = file_get_contents($p);
            $name = basename($p);
            $contents[$name] = $content;
            $indexes[$name] = Array();
        }

        // comparison of each pair of input file
        $names = array_keys($contents);
        $i = 0;
        while ($i<count($names)){
            $ni = $names[$i];
            $j = $i+1;
            while ($j<count($names)){
                $nj = $names[$j];
                try{
                    $comp = $this->compareTwoGpx($contents[$ni], $ni, $contents[$nj], $nj);
                    $indexes[$ni][$nj] = $comp[0];
                    $indexes[$nj][$ni] = $comp[1];
                }
                catch (\Exception $e) {
                    array_push($process_errors, '['.$ni.'|'.$nj.'] comparison error : '.$e->getMessage());
                }
                $j += 1;
            }
            $i += 1;
        }

        // from all comparison information, convert GPX to GeoJson with lots of meta-info
        foreach ($names as $ni){
            foreach ($names as $nj){
                if ($nj !== $ni){
                    if (array_key_exists($ni, $indexes) and array_key_exists($nj, $indexes[$ni])){
                        try{
                            $taggedGeo[$ni.$nj] = $this->gpxTracksToGeojson($contents[$ni], $ni, $indexes[$ni][$nj]);
                        }
                        catch (\Exception $e) {
                            array_push($process_errors, '['.$ni.'|'.$nj.'] geojson conversion error : '.$e->getMessage());
                        }
                    }
                }
            }
        }

        return $taggedGeo;
    }


    /*
     * build an index of divergence comparison
     */
    private function compareTwoGpx($gpxc1, $id1, $gpxc2, $id2){
        $gpx1 = new \SimpleXMLElement($gpxc1);
        $gpx2 = new \SimpleXMLElement($gpxc2);
        if (count($gpx1->trk) === 0){
            throw new \Exception('['.$id1.'] At least one track per GPX is needed');
        }
        else if (count($gpx2->trk) === 0){
            throw new \Exception('['.$id2.'] At least one track per GPX is needed');
        }
        else{
            $t1 = $gpx1->trk[0];
            $t2 = $gpx2->trk[0];
            if (count($t1->trkseg) === 0){
                throw new \Exception('['.$id1.'] At least one segment is needed per track');
            }
            else if(count($t2->trkseg) === 0){
                throw new \Exception('['.$id2.'] At least one segment is needed per track');
            }
            else{
                $p1 = $t1->trkseg[0]->trkpt;
                $p2 = $t2->trkseg[0]->trkpt;
            }
        }

        // index that will be returned
        $index1 = Array();
        $index2 = Array();
        // current points indexes
        $c1 = 0;
        $c2 = 0;
        # find first convergence point
        $conv = $this->findFirstConvergence($p1, $c1, $p2, $c2);

        // loop on 
        while ($conv !== null){
            // find first divergence point
            $c1 = $conv[0];
            $c2 = $conv[1];
            $div = $this->findFirstDivergence($p1, $c1, $p2, $c2);

            // if there isn't any divergence after
            if ($div === null){
                $conv = null;
                continue;
            }
            else{
                // if there is a divergence
                $c1 = $div[0];
                $c2 = $div[1];
                // find first convergence point again
                $conv = $this->findFirstConvergence($p1, $c1, $p2, $c2);
                if ($conv !== null){
                    if ($div[0]-2 > 0 and $div[1]-2 > 0){
                        $div = Array($div[0]-2, $div[1]-2);
                    }
                    $indexes = $this->compareBetweenDivAndConv($div, $conv, $p1, $p2, $id1, $id2);
                    array_push($index1, $indexes[0]);
                    array_push($index2, $indexes[1]);
                }
            }
        }
        return Array($index1, $index2);
    }

    /*
     * returns indexes of the first convergence point found 
     * from c1 and c2 in the point tables
     */
    private function findFirstConvergence($p1, $c1, $p2, $c2){
        $ct1 = $c1;
        while ($ct1 < count($p1)){
            $ct2 = $c2;
            while ($ct2 < count($p2) and distance($p1[$ct1], $p2[$ct2]) > 70){
                $ct2 += 1;
            }
            if ($ct2 < count($p2)){
                // we found a convergence point
                return Array($ct1, $ct2);
            }
            $ct1 += 1;
        }
        return null;
    }

    /*
     * find the first divergence by using findFirstConvergence
     */
    private function findFirstDivergence($p1, $c1, $p2, $c2){
        // we are in a convergence state so we need to advance
        $ct1 = $c1+1;
        $ct2 = $c2+1;
        $conv = $this->findFirstConvergence($p1, $ct1, $p2, $ct2);
        while ($conv !== null){
            // if it's still convergent, go on
            if ($conv[0] === $ct1 and $conv[1] === $ct2){
                $ct1 += 1;
                $ct2 += 1;
            }
            // if the convergence made only ct2 advance
            else if ($conv[0] === $ct1){
                $ct1 += 1;
                $ct2 = $conv[1]+1;
            }
            // if the convergence made only ct1 advance
            else if ($conv[1] === $ct2){
                $ct2 += 1;
                $ct1 = $conv[0]+1;
            }
            // the two tracks advanced to find next convergence, it's a divergence !
            else{
                return Array($ct1+1, $ct2+1);
            }

            $conv = $this->findFirstConvergence($p1, $ct1, $p2, $ct2);
        }

        return null;
    }

    /*
     * determine who's best in time and distance during this divergence
     */
    private function compareBetweenDivAndConv($div, $conv, $p1, $p2, $id1, $id2){
        $result1 = Array('divPoint'=>$div[0],
                'convPoint'=>$conv[0],
                'comparedTo'=>$id2,
                'isTimeBetter'=>null,
                'isDistanceBetter'=>null,
                'isPositiveDenivBetter'=>null,
                'positiveDeniv'=>null,
                'time'=>null,
                'distance'=>null
        );
        $result2 = Array('divPoint'=>$div[1],
                'convPoint'=>$conv[1],
                'comparedTo'=>$id1,
                'isTimeBetter'=>null,
                'isDistanceBetter'=>null,
                'isPositiveDenivBetter'=>null,
                'positiveDeniv'=>null,
                'time'=>null,
                'distance'=>null
        );
        // positive deniv
        $posden1 = 0;
        $posden2 = 0;
        $lastp = null;
        $upBegin = null;
        $isGoingUp = False;
        $lastDeniv = null;
        //for p in p1[div[0]:conv[0]+1]:
        $slice = Array();
        $ind = 0;
        foreach($p1 as $p){
            if ($ind >= $div[0] and $ind <= $conv[0]){
                array_push($slice, $p);
            }
            $ind++;
        }
        //$slice = array_slice($p1, $div[0], ($conv[0] - $div[0]) + 1);
        foreach($slice as $p){
            if (empty($p->ele)){
                throw new \Exception('Elevation data is needed for comparison in'.$id1);
            }
            if ($lastp !== null and (!empty($p->ele)) and (!empty($lastp->ele))){
                $deniv = (float)$p->ele - (float)$lastp->ele;
            }
            if ($lastDeniv !== null){
                // we start to go up
                if (($isGoingUp === False) and $deniv > 0){
                    $upBegin = (float)$lastp->ele;
                    $isGoingUp = True;
                }
                if (($isGoingUp === True) and $deniv < 0){
                    // we add the up portion
                    $posden1 += (float)$lastp->ele - $upBegin;
                    $isGoingUp = False;
                }
            }
            // update variables
            if ($lastp !== null and (!empty($p->ele)) and (!empty($lastp->ele))){
                $lastDeniv = $deniv;
            }
            $lastp = $p;
        }

        $lastp = null;
        $upBegin = null;
        $isGoingUp = False;
        $lastDeniv = null;
        //for p in p2[div[1]:conv[1]+1]:
        $slice = Array();
        $ind = 0;
        foreach($p2 as $p){
            if ($ind >= $div[1] and $ind <= $conv[1]){
                array_push($slice, $p);
            }
            $ind++;
        }
        //$slice2 = array_slice($p2, $div[1], ($conv[1] - $div[1]) + 1);
        foreach($slice as $p){
            if (empty($p->ele)){
                throw new \Exception('Elevation data is needed for comparison in '.$id2);
            }
            if ($lastp !== null and (!empty($p->ele)) and (!empty($lastp->ele))){
                $deniv = (float)$p->ele - (float)$lastp->ele;
            }
            if ($lastDeniv !== null){
                // we start a way up
                if (($isGoingUp === False) and $deniv > 0){
                    $upBegin = (float)$lastp->ele;
                    $isGoingUp = True;
                }
                if (($isGoingUp === True) and $deniv < 0){
                    // we add the up portion
                    $posden2 += (float)$lastp->ele - $upBegin;
                    $isGoingUp = False;
                }
            }
            // update variables
            if ($lastp !== null and (!empty($p->ele)) and (!empty($lastp->ele))){
                $lastDeniv = $deniv;
            }
            $lastp = $p;
        }

        $result1['isPositiveDenivBetter'] = ($posden1 < $posden2);
        $result1['positiveDeniv'] = $posden1;
        $result1['positiveDeniv_other'] = $posden2;
        $result2['isPositiveDenivBetter'] = ($posden2 <= $posden1);
        $result2['positiveDeniv'] = $posden2;
        $result2['positiveDeniv_other'] = $posden1;

        // distance
        $dist1 = 0;
        $dist2 = 0;
        $lastp = null;
        //for p in p1[div[0]:conv[0]+1]:
        $slice = Array();
        $ind = 0;
        foreach($p1 as $p){
            if ($ind >= $div[0] and $ind <= $conv[0]){
                array_push($slice, $p);
            }
            $ind++;
        }
        //$slice = array_slice($p1, $div[0], ($conv[0] - $div[0]) + 1);
        foreach($slice as $p){
            if ($lastp !== null){
                $dist1 += distance($lastp, $p);
            }
            $lastp = $p;
        }
        $lastp = null;
        //for p in p2[div[1]:conv[1]+1]:
        $slice = Array();
        $ind = 0;
        foreach($p2 as $p){
            if ($ind >= $div[1] and $ind <= $conv[1]){
                array_push($slice, $p);
            }
            $ind++;
        }
        //$slice2 = array_slice($p2, $div[1], ($conv[1] - $div[1]) + 1);
        foreach($slice as $p){
            if ($lastp !== null){
                $dist2 += distance($lastp, $p);
            }
            $lastp = $p;
        }

        $result1['isDistanceBetter'] = ($dist1 < $dist2);
        $result1['distance'] = $dist1;
        $result1['distance_other'] = $dist2;
        $result2['isDistanceBetter'] = ($dist1 >= $dist2);
        $result2['distance'] = $dist2;
        $result2['distance_other'] = $dist1;

        // time
        if (empty($p1[$div[0]]->time) or empty($p1[$conv[0]]->time)){
            throw new \Exception('Time data is needed for comparison in '.$id1);
        }
        $tdiv1 = new \DateTime($p1[$div[0]]->time);
        $tconv1 = new \DateTime($p1[$conv[0]]->time);
        $t1 = $tconv1->getTimestamp() - $tdiv1->getTimestamp();
        
        if (empty($p2[$div[1]]->time) or empty($p2[$conv[1]]->time)){
            throw new \Exception('Time data is needed for comparison in '.$id2);
        }
        $tdiv2 = new \DateTime($p2[$div[1]]->time);
        $tconv2 = new \DateTime($p2[$conv[1]]->time);
        $t2 = $tconv2->getTimestamp() - $tdiv2->getTimestamp();

        $t1str = format_time_seconds($t1);
        $t2str = format_time_seconds($t2);
        $result1['isTimeBetter'] = ($t1 < $t2);
        $result1['time'] = $t1str;
        $result1['time_other'] = $t2str;
        $result2['isTimeBetter'] = ($t1 >= $t2);
        $result2['time'] = $t2str;
        $result2['time_other'] = $t1str;

        return Array($result1, $result2);
    }

    /*
     * converts the gpx string input to a geojson string
     */
    private function gpxTracksToGeojson($gpx_content, $name, $divList){
        $currentlyInDivergence = False;
        $currentSectionPointList = Array();
        $currentProperties=Array('id'=>'',
                    'elevation'=>Array(),
                    'timestamps'=>'',
                    'quickerThan'=>Array(),
                    'shorterThan'=>Array(),
                    'longerThan'=>Array(),
                    'distanceOthers'=>Array(),
                    'timeOthers'=>Array(),
                    'positiveDenivOthers'=>Array(),
                    'slowerThan'=>Array(),
                    'morePositiveDenivThan'=>Array(),
                    'lessPositiveDenivThan'=>Array(),
                    'distance'=>null,
                    'positiveDeniv'=>null,
                    'time'=>null
        );

        $sections = Array();
        $properties = Array();

        $gpx = new \SimpleXMLElement($gpx_content);
        foreach($gpx->trk as $track){
            $featureList = Array();
            $lastPoint = null;
            $pointIndex = 0;
            foreach($track->trkseg as $segment){
                foreach($segment->trkpt as $point){
                    #print 'Point at ({0},{1}) -> {2}'.format(point.latitude, point.longitude, point.elevation)
                    if ($lastPoint !== null){
                        // is the point in a divergence ?
                        $isDiv = False;
                        foreach ($divList as $d){
                            if ($pointIndex > $d['divPoint'] and $pointIndex <= $d['convPoint']){
                                // we are in a divergence
                                $isDiv = True;
                                // is it the first point in div ?
                                if (! $currentlyInDivergence){
                                    // it is the first div point, we add previous section
                                    array_push($currentSectionPointList, $lastPoint);
                                    array_push($sections, $currentSectionPointList);
                                    // we update properties with lastPoint infos (the last in previous section)
                                    $currentProperties['id'] .= sprintf('%s',($pointIndex-1));
                                    array_push($currentProperties['elevation'], (float)$lastPoint->ele);
                                    $currentProperties['timestamps'] .= sprintf('%s', $lastPoint->time);
                                    // we add previous properties and reset tmp vars
                                    array_push($properties, $currentProperties);
                                    $currentSectionPointList = Array();
                                    // we add the last point that is the junction
                                    // between the two sections
                                    array_push($currentSectionPointList, $lastPoint);

                                    $currentProperties=Array('id'=>sprintf('%s-',($pointIndex-1)),
                                                'elevation'=>Array((float)$lastPoint->ele),
                                                'timestamps'=>sprintf('%s ; ',$lastPoint->time),
                                                'quickerThan'=>Array(),
                                                'shorterThan'=>Array(),
                                                'longerThan'=>Array(),
                                                'distanceOthers'=>Array(),
                                                'timeOthers'=>Array(),
                                                'positiveDenivOthers'=>Array(),
                                                'slowerThan'=>Array(),
                                                'morePositiveDenivThan'=>Array(),
                                                'lessPositiveDenivThan'=>Array(),
                                                'distance'=>null,
                                                'positiveDeniv'=>null,
                                                'time'=>null
                                    );
                                    $currentlyInDivergence = True;

                                    $comparedTo = $d['comparedTo'];
                                    $currentProperties['distance'] = $d['distance'];
                                    $currentProperties['time'] = $d['time'];
                                    $currentProperties['positiveDeniv'] = $d['positiveDeniv'];
                                    if ($d['isDistanceBetter']){
                                        array_push($currentProperties['shorterThan'], $comparedTo);
                                    }
                                    else{
                                        array_push($currentProperties['longerThan'], $comparedTo);
                                    }
                                    $currentProperties['distanceOthers'][$comparedTo] = $d['distance_other'];
                                    if ($d['isTimeBetter']){
                                        array_push($currentProperties['quickerThan'], $comparedTo);
                                    }
                                    else{
                                        array_push($currentProperties['slowerThan'], $comparedTo);
                                    }
                                    $currentProperties['timeOthers'][$comparedTo] = $d['time_other'];
                                    if ($d['isPositiveDenivBetter']){
                                        array_push($currentProperties['lessPositiveDenivThan'], $comparedTo);
                                    }
                                    else{
                                        array_push($currentProperties['morePositiveDenivThan'], $comparedTo);
                                    }
                                    $currentProperties['positiveDenivOthers'][$comparedTo] = $d['positiveDeniv_other'];
                                }
                            }
                        }

                        // if we were in a divergence and now are NOT in a divergence
                        if ($currentlyInDivergence and (! $isDiv)){
                            // it is the first NON div point, we add previous section
                            array_push($currentSectionPointList, $lastPoint);
                            array_push($currentSectionPointList, $point);
                            array_push($sections, $currentSectionPointList);
                            // we update properties with lastPoint infos (the last in previous section)
                            $currentProperties['id'] .= sprintf('%d', $pointIndex);
                            array_push($currentProperties['elevation'], (float)$point->ele);
                            $currentProperties['timestamps'] .= sprintf('%s', $point->time);
                            // we add previous properties and reset tmp vars
                            array_push($properties, $currentProperties);
                            $currentSectionPointList = Array();

                            $currentProperties=Array('id'=>sprintf('%s-',$pointIndex),
                                        'elevation'=>Array((float)$point->ele),
                                        'timestamps'=>sprintf('%s ; ',$point->time),
                                        'quickerThan'=>Array(),
                                        'shorterThan'=>Array(),
                                        'longerThan'=>Array(),
                                        'distanceOthers'=>Array(),
                                        'timeOthers'=>Array(),
                                        'positiveDenivOthers'=>Array(),
                                        'slowerThan'=>Array(),
                                        'morePositiveDenivThan'=>Array(),
                                        'lessPositiveDenivThan'=>Array(),
                                        'distance'=>null,
                                        'positiveDeniv'=>null,
                                        'time'=>null
                            );
                            $currentlyInDivergence = False;
                        }

                        array_push($currentSectionPointList, $point);
                    }
                    else{
                        // this is the first point
                        $currentProperties['id'] = 'begin-';
                        $currentProperties['timestamps'] = sprintf('%s ; ', $point->time);
                        array_push($currentProperties['elevation'], (float)$point->ele);
                    }

                    $lastPoint = $point;
                    $pointIndex += 1;
                }
            }

            if (count($currentSectionPointList) > 0){
                array_push($sections, $currentSectionPointList);
                $currentProperties['id'] .= 'end';
                $currentProperties['timestamps'] .= sprintf('%s', $lastPoint->time);
                array_push($currentProperties['elevation'], (float)$lastPoint->ele);
                array_push($properties, $currentProperties);
            }

            // for each section, we add a Feature
            foreach(range(0,count($sections)-1) as $i){
                $coords = Array();
                foreach ($sections[$i] as $p){
                    array_push($coords, Array((float)$p['lon'], (float)$p['lat']));
                }
                array_push($featureList,
                    Array("type"=>"Feature",
                        "id"=>sprintf('%s',$i),
                        "properties"=>$properties[$i],
                        "geometry"=>Array("coordinates"=>$coords,
                                    "type"=>"LineString")
                    )
                );
            }

            //fc = geojson.FeatureCollection(featureList, id=name)
            $fc = Array("type"=>"FeatureCollection",
                "features"=>$featureList,
                "id"=>$name
            );
            return json_encode($fc);
        }
    }

    /*
     * return global stats for each track in the tempdir
     */
    private function getStats($tempdir, &$process_errors){
        $STOPPED_SPEED_THRESHOLD = 0.9;
        $paths = globRecursive($tempdir, '*.gpx', False);
        $stats = Array();

        foreach ($paths as $path){
            try{
                $gpx_content = file_get_contents($path);
                $gpx = new \SimpleXMLElement($gpx_content);

                $nbpoints = 0;
                $total_distance = 0;
                $total_duration = 'null';
                $date_begin = null;
                $date_end = null;
                $pos_elevation = 0;
                $neg_elevation = 0;
                $max_speed = 0;
                $avg_speed = 'null';
                $moving_time = 0;
                $moving_distance = 0;
                $stopped_distance = 0;
                $moving_max_speed = 0;
                $moving_avg_speed = 0;
                $stopped_time = 0;

                $isGoingUp = False;
                $lastDeniv = null;
                $upBegin = null;
                $downBegin = null;
                $lastTime = null;

                // TRACKS
                foreach($gpx->trk as $track){
                    foreach($track->trkseg as $segment){
                        $lastPoint = null;
                        $lastTime = null;
                        $pointIndex = 0;
                        $lastDeniv = null;
                        foreach($segment->trkpt as $point){
                            $nbpoints++;
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
                            if ($pointIndex === 0){
                                if ($pointtime !== null and ($date_begin === null or $pointtime < $date_begin)){
                                    $date_begin = $pointtime;
                                }
                                $downBegin = $pointele;
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
                    $lastPoint = null;
                    $lastTime = null;
                    $pointIndex = 0;
                    $lastDeniv = null;
                    foreach($route->rtept as $point){
                        $nbpoints++;
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
                        if ($pointIndex === 0){
                            if ($pointtime !== null and ($date_begin === null or $pointtime < $date_begin)){
                                $date_begin = $pointtime;
                            }
                            $downBegin = $pointele;
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
                            if ($isGoingUp === False and deniv > 0){
                                $upBegin = (float)$lastPoint->ele;
                                $isGoingUp = True;
                                $neg_elevation += ($downBegin - (float)$lastPoint->ele);
                            }
                            if ($isGoingUp === True and deniv < 0){
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

                $stats[basename($path)] = Array(
                    'length_2d'=>number_format($total_distance/1000,3, '.', ''),
                    'length_3d'=>number_format($total_distance/1000,3, '.', ''),
                    'moving_time'=>format_time_seconds($moving_time),
                    'stopped_time'=>format_time_seconds($stopped_time),
                    'max_speed'=>number_format($max_speed,2, '.', ''),
                    'moving_avg_speed'=>number_format($moving_avg_speed,2, '.', ''),
                    'avg_speed'=>$avg_speed,
                    'total_uphill'=>$pos_elevation,
                    'total_downhill'=>$neg_elevation,
                    'started'=>$date_begin,
                    'ended'=>$date_end,
                    'nbpoints'=>$nbpoints
                );
            }
            catch (\Exception $e) {
                array_push($process_errors, '['.basename($path).'] stats compute error : '.$e->getMessage());
            }
        }

        return $stats;
    }

}
