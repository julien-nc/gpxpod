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
use OCP\IServerContainer;
use OCP\IInitialStateService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

require_once('utils.php');

class ComparisonController extends Controller {

    private $userId;
    private $userfolder;
    private $config;
    private $dbconnection;
    private $dbtype;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IAppManager $appManager,
                                IInitialStateService $initialStateService,
                                $UserId) {
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->appName = $AppName;
        $this->initialStateService = $initialStateService;
        $this->dbtype = $config->getSystemValue('dbtype');
        if ($this->dbtype === 'pgsql') {
            $this->dbdblquotes = '"';
        } else {
            $this->dbdblquotes = '';
        }
        if ($UserId !== null && $UserId !== '' && $serverContainer !== null) {
            $this->userfolder = $serverContainer->getUserFolder($UserId);
        }
        $this->config = $config;
        $this->dbconnection = \OC::$server->getDatabaseConnection();
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str): string {
        return $this->dbconnection->quote($str);
    }

    private function getUserTileServers($type): array {
        // custom tile servers management
        $sqlts = '
            SELECT servername, url
            FROM *PREFIX*gpxpod_tile_servers
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND type='.$this->db_quote_escape_string($type).' ;';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $tss = [];
        while ($row = $req->fetch()) {
            $tss[$row['servername']] = $row['url'];
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
    public function gpxvcomp(): TemplateResponse {
        $userFolder = \OC::$server->getUserFolder();

        $gpxs = [];

        // gpx in GET parameters
        if (!empty($_GET)) {
            for ($i = 1; $i <= 10; $i++) {
                if (isset($_GET['path'.$i]) && $_GET['path'.$i] !== '') {
                    $cleanpath = str_replace(array('../', '..\\'), '', $_GET['path'.$i]);
                    $file = $userFolder->get($cleanpath);
                    $content = $file->getContent();
                    $gpxs[$cleanpath] = $content;
                }
            }
        }

        $process_errors = [];

        if (count($gpxs) > 0) {
            $geojson = $this->processTrackComparison($gpxs, $process_errors);
            $stats = $this->getStats($gpxs, $process_errors);
        }
        $this->initialStateService->provideInitialState($this->appName, 'geojson', $geojson);

        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');

        // PARAMS to send to template

        require_once('tileservers.php');
        $params = [
            'error_output' => $process_errors,
            'gpxs' => $gpxs,
            'stats' => $stats,
            'basetileservers' => $baseTileServers,
            'tileservers' => $tss,
            'overlayservers' => $oss
        ];
        $response = new TemplateResponse('gpxpod', 'compare', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            // ->addAllowedScriptDomain('*')
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
    public function gpxvcompp(): TemplateResponse {
        $gpxs = [];

        // Get uploaded files

        // we uploaded a gpx by the POST form
        if (!empty($_POST)) {
            for ($i = 1; $i <= 10; $i++) {
                if (isset($_FILES["gpx$i"]) && $_FILES["gpx$i"]['name'] !== "") {
                    $name = str_replace(' ', '_', $_FILES["gpx$i"]['name']);
                    $content = file_get_contents($_FILES["gpx$i"]['tmp_name']);
                    $gpxs[$name] = $content;
                }
            }
        }

        // Process gpx files

        $process_errors = [];

        if (count($gpxs)>0) {
            $geojson = $this->processTrackComparison($gpxs, $process_errors);
            $stats = $this->getStats($gpxs, $process_errors);
        }
        $this->initialStateService->provideInitialState($this->appName, 'geojson', $geojson);

        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');

        // PARAMS to send to template

        require_once('tileservers.php');
        $params = [
            'error_output' => $process_errors,
            'gpxs' => $gpxs,
            'stats' => $stats,
            'basetileservers' => $baseTileServers,
            'tileservers' => $tss,
            'overlayservers' => $oss
        ];
        $response = new TemplateResponse('gpxpod', 'compare', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
            // ->addAllowedScriptDomain('*')
            ->addAllowedConnectDomain('*');
        $response->setContentSecurityPolicy($csp);
        return $response;
    }

    private function processTrackComparison($contents, &$process_errors): array {
        $indexes = [];
        $taggedGeo = [];

        foreach ($contents as $fname => $content) {
            $indexes[$fname] = [];
        }

        // comparison of each pair of input file
        $names = array_keys($contents);
        $i = 0;
        while ($i < count($names)) {
            $ni = $names[$i];
            $j = $i+1;
            while ($j<count($names)) {
                $nj = $names[$j];
                try {
                    $comp = $this->compareTwoGpx($contents[$ni], $ni, $contents[$nj], $nj);
                    $indexes[$ni][$nj] = $comp[0];
                    $indexes[$nj][$ni] = $comp[1];
                }
                catch (\Exception $e) {
                    $process_errors[] = '['.$ni.'|'.$nj.'] comparison error : '.$e->getMessage();
                }
                $j += 1;
            }
            $i += 1;
        }

        // from all comparison information, convert GPX to GeoJson with lots of meta-info
        foreach ($names as $ni) {
            foreach ($names as $nj) {
                if ($nj !== $ni) {
                    if (array_key_exists($ni, $indexes) && array_key_exists($nj, $indexes[$ni])) {
                        try {
                            $taggedGeo[$ni.$nj] = $this->gpxTracksToGeojson($contents[$ni], $ni, $indexes[$ni][$nj]);
                        }
                        catch (\Exception $e) {
                            $process_errors[] = '['.$ni.'|'.$nj.'] geojson conversion error : '.$e->getMessage();
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
    private function compareTwoGpx($gpxc1, $id1, $gpxc2, $id2): array {
        $gpx1 = new \SimpleXMLElement($gpxc1);
        $gpx2 = new \SimpleXMLElement($gpxc2);
        if (count($gpx1->trk) === 0) {
            throw new \Exception('['.$id1.'] At least one track per GPX is needed');
        } elseif (count($gpx2->trk) === 0) {
            throw new \Exception('['.$id2.'] At least one track per GPX is needed');
        } else {
            $t1 = $gpx1->trk[0];
            $t2 = $gpx2->trk[0];
            if (count($t1->trkseg) === 0) {
                throw new \Exception('['.$id1.'] At least one segment is needed per track');
            } elseif(count($t2->trkseg) === 0) {
                throw new \Exception('['.$id2.'] At least one segment is needed per track');
            } else {
                $p1 = $t1->trkseg[0]->trkpt;
                $p2 = $t2->trkseg[0]->trkpt;
            }
        }

        // index that will be returned
        $index1 = [];
        $index2 = [];
        // current points indexes
        $c1 = 0;
        $c2 = 0;
        # find first convergence point
        $conv = $this->findFirstConvergence($p1, $c1, $p2, $c2);

        // loop on
        while ($conv !== null) {
            // find first divergence point
            $c1 = $conv[0];
            $c2 = $conv[1];
            $div = $this->findFirstDivergence($p1, $c1, $p2, $c2);

            // if there isn't any divergence after
            if ($div === null) {
                $conv = null;
                continue;
            } else {
                // if there is a divergence
                $c1 = $div[0];
                $c2 = $div[1];
                // find first convergence point again
                $conv = $this->findFirstConvergence($p1, $c1, $p2, $c2);
                if ($conv !== null) {
                    if ($div[0]-2 > 0 && $div[1]-2 > 0) {
                        $div = [
                            $div[0]-2,
                            $div[1]-2
                        ];
                    }
                    $indexes = $this->compareBetweenDivAndConv($div, $conv, $p1, $p2, $id1, $id2);
                    $index1[] = $indexes[0];
                    $index2[] = $indexes[1];
                }
            }
        }
        return [$index1, $index2];
    }

    /*
     * returns indexes of the first convergence point found
     * from c1 and c2 in the point tables
     */
    private function findFirstConvergence($p1, $c1, $p2, $c2): ?array {
        $ct1 = $c1;
        while ($ct1 < count($p1)) {
            $ct2 = $c2;
            while ($ct2 < count($p2) && distance($p1[$ct1], $p2[$ct2]) > 70) {
                $ct2 += 1;
            }
            if ($ct2 < count($p2)) {
                // we found a convergence point
                return [$ct1, $ct2];
            }
            $ct1 += 1;
        }
        return null;
    }

    /*
     * find the first divergence by using findFirstConvergence
     */
    private function findFirstDivergence($p1, $c1, $p2, $c2): ?array {
        // we are in a convergence state so we need to advance
        $ct1 = $c1 + 1;
        $ct2 = $c2 + 1;
        $conv = $this->findFirstConvergence($p1, $ct1, $p2, $ct2);
        while ($conv !== null) {
            // if it's still convergent, go on
            if ($conv[0] === $ct1 && $conv[1] === $ct2) {
                $ct1 += 1;
                $ct2 += 1;
            } elseif ($conv[0] === $ct1) {
                // if the convergence made only ct2 advance
                $ct1 += 1;
                $ct2 = $conv[1] + 1;
            } elseif ($conv[1] === $ct2) {
                // if the convergence made only ct1 advance
                $ct2 += 1;
                $ct1 = $conv[0] + 1;
            } else {
                // the two tracks advanced to find next convergence, it's a divergence !
                return [
                    $ct1 + 1,
                    $ct2 + 1
                ];
            }

            $conv = $this->findFirstConvergence($p1, $ct1, $p2, $ct2);
        }

        return null;
    }

    /*
     * determine who's best in time and distance during this divergence
     */
    private function compareBetweenDivAndConv($div, $conv, $p1, $p2, $id1, $id2): array {
        $result1 = [
            'divPoint' => $div[0],
            'convPoint' => $conv[0],
            'comparedTo' => $id2,
            'isTimeBetter' => null,
            'isDistanceBetter' => null,
            'isPositiveDenivBetter' => null,
            'positiveDeniv' => null,
            'time' => null,
            'distance' => null
        ];
        $result2 = [
            'divPoint' => $div[1],
            'convPoint' => $conv[1],
            'comparedTo' => $id1,
            'isTimeBetter' => null,
            'isDistanceBetter' => null,
            'isPositiveDenivBetter' => null,
            'positiveDeniv' => null,
            'time' => null,
            'distance' => null
        ];
        // positive deniv
        $posden1 = 0;
        $posden2 = 0;
        $lastp = null;
        $upBegin = null;
        $isGoingUp = false;
        $lastDeniv = null;
        //for p in p1[div[0]:conv[0]+1]:
        $slice = [];
        $ind = 0;
        foreach ($p1 as $p) {
            if ($ind >= $div[0] && $ind <= $conv[0]) {
                $slice[] = $p;
            }
            $ind++;
        }
        //$slice = array_slice($p1, $div[0], ($conv[0] - $div[0]) + 1);
        foreach ($slice as $p) {
            if (empty($p->ele)) {
                throw new \Exception('Elevation data is needed for comparison in'.$id1);
            }
            if ($lastp !== null && (!empty($p->ele)) && (!empty($lastp->ele))) {
                $deniv = (float)$p->ele - (float)$lastp->ele;
            }
            if ($lastDeniv !== null) {
                // we start to go up
                if (($isGoingUp === false) && $deniv > 0) {
                    $upBegin = (float)$lastp->ele;
                    $isGoingUp = true;
                }
                if (($isGoingUp === true) && $deniv < 0) {
                    // we add the up portion
                    $posden1 += (float)$lastp->ele - $upBegin;
                    $isGoingUp = false;
                }
            }
            // update variables
            if ($lastp !== null && (!empty($p->ele)) && (!empty($lastp->ele))) {
                $lastDeniv = $deniv;
            }
            $lastp = $p;
        }

        $lastp = null;
        $upBegin = null;
        $isGoingUp = false;
        $lastDeniv = null;
        //for p in p2[div[1]:conv[1]+1]:
        $slice = [];
        $ind = 0;
        foreach ($p2 as $p) {
            if ($ind >= $div[1] && $ind <= $conv[1]) {
                $slice[] = $p;
            }
            $ind++;
        }
        //$slice2 = array_slice($p2, $div[1], ($conv[1] - $div[1]) + 1);
        foreach ($slice as $p) {
            if (empty($p->ele)) {
                throw new \Exception('Elevation data is needed for comparison in '.$id2);
            }
            if ($lastp !== null && (!empty($p->ele)) && (!empty($lastp->ele))) {
                $deniv = (float)$p->ele - (float)$lastp->ele;
            }
            if ($lastDeniv !== null) {
                // we start a way up
                if (($isGoingUp === false) && $deniv > 0) {
                    $upBegin = (float)$lastp->ele;
                    $isGoingUp = true;
                }
                if (($isGoingUp === true) && $deniv < 0) {
                    // we add the up portion
                    $posden2 += (float)$lastp->ele - $upBegin;
                    $isGoingUp = false;
                }
            }
            // update variables
            if ($lastp !== null && (!empty($p->ele)) && (!empty($lastp->ele))) {
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
        $slice = [];
        $ind = 0;
        foreach ($p1 as $p) {
            if ($ind >= $div[0] && $ind <= $conv[0]) {
                $slice[] = $p;
            }
            $ind++;
        }
        //$slice = array_slice($p1, $div[0], ($conv[0] - $div[0]) + 1);
        foreach ($slice as $p) {
            if ($lastp !== null) {
                $dist1 += distance($lastp, $p);
            }
            $lastp = $p;
        }
        $lastp = null;
        //for p in p2[div[1]:conv[1]+1]:
        $slice = [];
        $ind = 0;
        foreach ($p2 as $p) {
            if ($ind >= $div[1] && $ind <= $conv[1]) {
                $slice[] = $p;
            }
            $ind++;
        }
        //$slice2 = array_slice($p2, $div[1], ($conv[1] - $div[1]) + 1);
        foreach ($slice as $p) {
            if ($lastp !== null) {
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
        if (empty($p1[$div[0]]->time) || empty($p1[$conv[0]]->time)) {
            throw new \Exception('Time data is needed for comparison in '.$id1);
        }
        $tdiv1 = new \DateTime($p1[$div[0]]->time);
        $tconv1 = new \DateTime($p1[$conv[0]]->time);
        $t1 = $tconv1->getTimestamp() - $tdiv1->getTimestamp();

        if (empty($p2[$div[1]]->time) || empty($p2[$conv[1]]->time)) {
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

        return [$result1, $result2];
    }

    /*
     * converts the gpx string input to a geojson string
     */
    private function gpxTracksToGeojson($gpx_content, $name, $divList): string {
        $currentlyInDivergence = false;
        $currentSectionPointList = [];
        $currentProperties = [
            'id' => '',
            'elevation' => [],
            'timestamps' => '',
            'quickerThan' => [],
            'shorterThan' => [],
            'longerThan' => [],
            'distanceOthers' => [],
            'timeOthers' => [],
            'positiveDenivOthers' => [],
            'slowerThan' => [],
            'morePositiveDenivThan' => [],
            'lessPositiveDenivThan' => [],
            'distance' => null,
            'positiveDeniv' => null,
            'time' => null
        ];

        $sections = [];
        $properties = [];

        $gpx = new \SimpleXMLElement($gpx_content);
        foreach ($gpx->trk as $track) {
            $featureList = [];
            $lastPoint = null;
            $pointIndex = 0;
            foreach ($track->trkseg as $segment) {
                foreach ($segment->trkpt as $point) {
                    #print 'Point at ({0},{1}) -> {2}'.format(point.latitude, point.longitude, point.elevation)
                    if ($lastPoint !== null) {
                        // is the point in a divergence ?
                        $isDiv = false;
                        foreach ($divList as $d) {
                            if ($pointIndex > $d['divPoint'] && $pointIndex <= $d['convPoint']) {
                                // we are in a divergence
                                $isDiv = true;
                                // is it the first point in div ?
                                if (!$currentlyInDivergence) {
                                    // it is the first div point, we add previous section
                                    $currentSectionPointList[] = $lastPoint;
                                    $sections[] = $currentSectionPointList;
                                    // we update properties with lastPoint infos (the last in previous section)
                                    $currentProperties['id'] .= sprintf('%s',($pointIndex-1));
                                    $currentProperties['elevation'][] = (float) $lastPoint->ele;
                                    $currentProperties['timestamps'] .= sprintf('%s', $lastPoint->time);
                                    // we add previous properties and reset tmp vars
                                    $properties[] = $currentProperties;
                                    $currentSectionPointList = [];
                                    // we add the last point that is the junction
                                    // between the two sections
                                    $currentSectionPointList[] = $lastPoint;

                                    $currentProperties = [
                                        'id' => sprintf('%s-',($pointIndex-1)),
                                        'elevation' => [(float) $lastPoint->ele],
                                        'timestamps' => sprintf('%s ; ',$lastPoint->time),
                                        'quickerThan' => [],
                                        'shorterThan' => [],
                                        'longerThan' => [],
                                        'distanceOthers' => [],
                                        'timeOthers' => [],
                                        'positiveDenivOthers' => [],
                                        'slowerThan' => [],
                                        'morePositiveDenivThan' => [],
                                        'lessPositiveDenivThan' => [],
                                        'distance' => null,
                                        'positiveDeniv' => null,
                                        'time' => null
                                    ];
                                    $currentlyInDivergence = true;

                                    $comparedTo = $d['comparedTo'];
                                    $currentProperties['distance'] = $d['distance'];
                                    $currentProperties['time'] = $d['time'];
                                    $currentProperties['positiveDeniv'] = $d['positiveDeniv'];
                                    if ($d['isDistanceBetter']) {
                                        $currentProperties['shorterThan'][] = $comparedTo;
                                    } else {
                                        $currentProperties['longerThan'][] = $comparedTo;
                                    }
                                    $currentProperties['distanceOthers'][$comparedTo] = $d['distance_other'];
                                    if ($d['isTimeBetter']) {
                                        $currentProperties['quickerThan'][] = $comparedTo;
                                    } else {
                                        $currentProperties['slowerThan'][] = $comparedTo;
                                    }
                                    $currentProperties['timeOthers'][$comparedTo] = $d['time_other'];
                                    if ($d['isPositiveDenivBetter']) {
                                        $currentProperties['lessPositiveDenivThan'][] = $comparedTo;
                                    } else {
                                        $currentProperties['morePositiveDenivThan'][] = $comparedTo;
                                    }
                                    $currentProperties['positiveDenivOthers'][$comparedTo] = $d['positiveDeniv_other'];
                                }
                            }
                        }

                        // if we were in a divergence and now are NOT in a divergence
                        if ($currentlyInDivergence && (! $isDiv)) {
                            // it is the first NON div point, we add previous section
                            $currentSectionPointList[] = $lastPoint;
                            $currentSectionPointList[] = $point;
                            $sections[] = $currentSectionPointList;
                            // we update properties with lastPoint infos (the last in previous section)
                            $currentProperties['id'] .= sprintf('%d', $pointIndex);
                            $currentProperties['elevation'][] = (float) $point->ele;
                            $currentProperties['timestamps'] .= sprintf('%s', $point->time);
                            // we add previous properties and reset tmp vars
                            $properties[] = $currentProperties;
                            $currentSectionPointList = [];

                            $currentProperties = [
                                'id' => sprintf('%s-',$pointIndex),
                                'elevation' => [(float) $point->ele],
                                'timestamps' => sprintf('%s ; ',$point->time),
                                'quickerThan' => [],
                                'shorterThan' => [],
                                'longerThan' => [],
                                'distanceOthers' => [],
                                'timeOthers' => [],
                                'positiveDenivOthers' => [],
                                'slowerThan' => [],
                                'morePositiveDenivThan' => [],
                                'lessPositiveDenivThan' => [],
                                'distance' => null,
                                'positiveDeniv' => null,
                                'time' => null
                            ];
                            $currentlyInDivergence = false;
                        }

                        $currentSectionPointList[] = $point;
                    } else {
                        // this is the first point
                        $currentProperties['id'] = 'begin-';
                        $currentProperties['timestamps'] = sprintf('%s ; ', $point->time);
                        $currentProperties['elevation'][] = (float) $point->ele;
                    }

                    $lastPoint = $point;
                    $pointIndex += 1;
                }
            }

            if (count($currentSectionPointList) > 0) {
                $sections[] = $currentSectionPointList;
                $currentProperties['id'] .= 'end';
                $currentProperties['timestamps'] .= sprintf('%s', $lastPoint->time);
                $currentProperties['elevation'][] = (float) $lastPoint->ele;
                $properties[] = $currentProperties;
            }

            // for each section, we add a Feature
            foreach (range(0,count($sections)-1) as $i) {
                $coords = [];
                foreach ($sections[$i] as $p) {
                    $coords[] = [
                        (float) $p['lon'],
                        (float) $p['lat']
                    ];
                }
                $featureList[] = [
                    'type' => 'Feature',
                    'id' => sprintf('%s',$i),
                    'properties' => $properties[$i],
                    'geometry' => [
                        'coordinates' => $coords,
                        'type' => 'LineString'
                    ],
                ];
            }

            //fc = geojson.FeatureCollection(featureList, id=name)
            $fc = [
                'type' => 'FeatureCollection',
                'features' => $featureList,
                'id' => $name,
            ];
            return json_encode($fc);
        }
    }

    /*
     * return global stats for each track
     */
    private function getStats($contents, &$process_errors): array {
        $STOPPED_SPEED_THRESHOLD = 0.9;
        $stats = [];

        foreach ($contents as $name => $gpx_content) {
            try {
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

                $isGoingUp = false;
                $lastDeniv = null;
                $upBegin = null;
                $downBegin = null;
                $lastTime = null;

                // TRACKS
                foreach ($gpx->trk as $track) {
                    foreach ($track->trkseg as $segment) {
                        $lastPoint = null;
                        $lastTime = null;
                        $pointIndex = 0;
                        $lastDeniv = null;
                        foreach ($segment->trkpt as $point) {
                            $nbpoints++;
                            if (empty($point->ele)) {
                                $pointele = null;
                            } else {
                                $pointele = (float)$point->ele;
                            }
                            if (empty($point->time)) {
                                $pointtime = null;
                            } else {
                                $pointtime = new \DateTime($point->time);
                            }
                            if ($lastPoint !== null && (!empty($lastPoint->ele))) {
                                $lastPointele = (float)$lastPoint->ele;
                            } else {
                                $lastPointele = null;
                            }
                            if ($lastPoint !== null && (!empty($lastPoint->time))) {
                                $lastTime = new \DateTime($lastPoint->time);
                            } else {
                                $lastTime = null;
                            }
                            if ($lastPoint !== null) {
                                $distToLast = distance($lastPoint, $point);
                            } else {
                                $distToLast = null;
                            }
                            if ($pointIndex === 0) {
                                if ($pointtime !== null && ($date_begin === null || $pointtime < $date_begin)) {
                                    $date_begin = $pointtime;
                                }
                                $downBegin = $pointele;
                            }

                            if ($lastPoint !== null && $pointtime !== null && $lastTime !== null) {
                                $t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

                                $speed = 0;
                                if ($t > 0) {
                                    $speed = $distToLast / $t;
                                    $speed = $speed / 1000;
                                    $speed = $speed * 3600;
                                    if ($speed > $max_speed) {
                                        $max_speed = $speed;
                                    }
                                }

                                if ($speed <= $STOPPED_SPEED_THRESHOLD) {
                                    $stopped_time += $t;
                                    $stopped_distance += $distToLast;
                                } else {
                                    $moving_time += $t;
                                    $moving_distance += $distToLast;
                                }
                            }
                            if ($lastPoint !== null) {
                                $total_distance += $distToLast;
                            }
                            if ($lastPoint !== null && $pointele !== null && (!empty($lastPoint->ele))) {
                                $deniv = $pointele - (float)$lastPoint->ele;
                            }
                            if ($lastDeniv !== null && $pointele !== null && $lastPoint !== null && (!empty($lastPoint->ele))) {
                                // we start to go up
                                if ($isGoingUp === false && $deniv > 0) {
                                    $upBegin = (float)$lastPoint->ele;
                                    $isGoingUp = true;
                                    $neg_elevation += ($downBegin - (float)$lastPoint->ele);
                                }
                                if ($isGoingUp === true && $deniv < 0) {
                                    // we add the up portion
                                    $pos_elevation += ((float)$lastPointele - $upBegin);
                                    $isGoingUp = false;
                                    $downBegin = (float)$lastPoint->ele;
                                }
                            }
                            // update vars
                            if ($lastPoint !== null && $pointele !== null && (!empty($lastPoint->ele))) {
                                $lastDeniv = $deniv;
                            }

                            $lastPoint = $point;
                            $pointIndex += 1;
                        }
                    }

                    if ($lastTime !== null && ($date_end === null || $lastTime > $date_end)) {
                        $date_end = $lastTime;
                    }
                }

                # ROUTES
                foreach ($gpx->rte as $route) {
                    $lastPoint = null;
                    $lastTime = null;
                    $pointIndex = 0;
                    $lastDeniv = null;
                    foreach ($route->rtept as $point) {
                        $nbpoints++;
                        if (empty($point->ele)) {
                            $pointele = null;
                        } else {
                            $pointele = (float)$point->ele;
                        }
                        if (empty($point->time)) {
                            $pointtime = null;
                        } else {
                            $pointtime = new \DateTime($point->time);
                        }
                        if ($lastPoint !== null && (!empty($lastPoint->ele))) {
                            $lastPointele = (float)$lastPoint->ele;
                        } else {
                            $lastPointele = null;
                        }
                        if ($lastPoint !== null && (!empty($lastPoint->time))) {
                            $lastTime = new \DateTime($lastPoint->time);
                        } else {
                            $lastTime = null;
                        }
                        if ($lastPoint !== null) {
                            $distToLast = distance($lastPoint, $point);
                        } else {
                            $distToLast = null;
                        }
                        if ($pointIndex === 0) {
                            if ($pointtime !== null && ($date_begin === null || $pointtime < $date_begin)) {
                                $date_begin = $pointtime;
                            }
                            $downBegin = $pointele;
                        }

                        if ($lastPoint !== null && $pointtime !== null && $lastTime !== null) {
                            $t = abs($lastTime->getTimestamp() - $pointtime->getTimestamp());

                            $speed = 0;
                            if ($t > 0) {
                                $speed = $distToLast / $t;
                                $speed = $speed / 1000;
                                $speed = $speed * 3600;
                                if ($speed > $max_speed) {
                                    $max_speed = $speed;
                                }
                            }

                            if ($speed <= $STOPPED_SPEED_THRESHOLD) {
                                $stopped_time += $t;
                                $stopped_distance += $distToLast;
                            } else {
                                $moving_time += $t;
                                $moving_distance += $distToLast;
                            }
                        }
                        if ($lastPoint !== null) {
                            $total_distance += $distToLast;
                        }
                        if ($lastPoint !== null && $pointele !== null && (!empty($lastPoint->ele))) {
                            $deniv = $pointele - (float)$lastPoint->ele;
                        }
                        if ($lastDeniv !== null && $pointele !== null && $lastPoint !== null && (!empty($lastPoint->ele))) {
                            // we start to go up
                            if ($isGoingUp === false && $deniv > 0) {
                                $upBegin = (float)$lastPoint->ele;
                                $isGoingUp = true;
                                $neg_elevation += ($downBegin - (float)$lastPoint->ele);
                            }
                            if ($isGoingUp === true && $deniv < 0) {
                                // we add the up portion
                                $pos_elevation += ((float)$lastPointele - $upBegin);
                                $isGoingUp = false;
                                $downBegin = (float)$lastPoint->ele;
                            }
                        }
                        // update vars
                        if ($lastPoint !== null && $pointele !== null && (!empty($lastPoint->ele))) {
                            $lastDeniv = $deniv;
                        }

                        $lastPoint = $point;
                        $pointIndex += 1;
                    }

                    if ($lastTime !== null && ($date_end === null || $lastTime > $date_end)) {
                        $date_end = $lastTime;
                    }
                }

                # TOTAL STATS : duration, avg speed, avg_moving_speed
                if ($date_end !== null && $date_begin !== null) {
                    $totsec = abs($date_end->getTimestamp() - $date_begin->getTimestamp());
                    $total_duration = sprintf('%02d:%02d:%02d', (int)($totsec/3600), (int)(($totsec % 3600)/60), $totsec % 60);
                    if ($totsec === 0) {
                        $avg_speed = 0;
                    } else {
                        $avg_speed = $total_distance / $totsec;
                        $avg_speed = $avg_speed / 1000;
                        $avg_speed = $avg_speed * 3600;
                        $avg_speed = sprintf('%.2f', $avg_speed);
                    }
                } else {
                    $total_duration = "???";
                }

                // determination of real moving average speed from moving time
                $moving_avg_speed = 0;
                if ($moving_time > 0) {
                    $moving_avg_speed = $total_distance / $moving_time;
                    $moving_avg_speed = $moving_avg_speed / 1000;
                    $moving_avg_speed = $moving_avg_speed * 3600;
                    $moving_avg_speed = sprintf('%.2f', $moving_avg_speed);
                }

                if ($date_begin === null) {
                    $date_begin = '';
                } else {
                    $date_begin = $date_begin->format('Y-m-d H:i:s');
                }
                if ($date_end === null) {
                    $date_end = '';
                } else {
                    $date_end = $date_end->format('Y-m-d H:i:s');
                }

                $stats[$name] = [
                    'length_2d' => number_format($total_distance / 1000, 3, '.', ''),
                    'length_3d' => number_format($total_distance / 1000, 3, '.', ''),
                    'moving_time' => format_time_seconds($moving_time),
                    'stopped_time' => format_time_seconds($stopped_time),
                    'max_speed' => number_format($max_speed, 2, '.', ''),
                    'moving_avg_speed' => number_format($moving_avg_speed, 2, '.', ''),
                    'avg_speed' => $avg_speed,
                    'total_uphill' => $pos_elevation,
                    'total_downhill' => $neg_elevation,
                    'started' => $date_begin,
                    'ended' => $date_end,
                    'nbpoints' => $nbpoints,
                ];
            }
            catch (\Exception $e) {
                $process_errors[] = '['.$name.'] stats compute error : '.$e->getMessage();
            }
        }

        return $stats;
    }

}
