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
use \OCP\IL10N;
use \OCP\ILogger;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

require_once('conversion.php');
require_once('utils.php');

class PageController extends Controller {

    private $userId;
    private $config;
    private $appVersion;
    private $shareManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $appPath;
    private $extensions;
    private $logger;
    private $trans;
    private $upperExtensions;
    private $gpxpodCachePath;
    protected $appName;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager,
                                IAppManager $appManager, ILogger $logger, IL10N $trans){
        parent::__construct($AppName, $request);
        $this->appVersion = $config->getAppValue('gpxpod', 'installed_version');
        $this->logger = $logger;
        $this->trans = $trans;
        $this->appName = $AppName;
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('gpxpod');
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
        $this->gpxpodCachePath = $this->config->getSystemValue('datadirectory').'/gpxpod';
        if (!is_dir($this->gpxpodCachePath)) {
            mkdir($this->gpxpodCachePath);
        }
        //$this->shareManager = \OC::$server->getShareManager();
        $this->shareManager = $shareManager;

        $this->extensions = Array(
            '.kml'=>'kml',
            '.gpx'=>'',
            '.tcx'=>'gtrnctr',
            '.igc'=>'igc',
            '.jpg'=>'',
            '.fit'=>'garmin_fit'
        );
        $this->upperExtensions = array_map('strtoupper', array_keys($this->extensions));
    }

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    private function getUserTileServers($type, $username='', $layername=''){
        $user = $username;
        if ($user === '') {
            $user = $this->userId;
        }
        // custom tile servers management
        $sqlts = 'SELECT servername, type, url, layers, version, format, opacity, transparent, minzoom, maxzoom, attribution FROM *PREFIX*gpxpod_tile_servers ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).' ';
        // if username is set, we filter anyway
        if ($username !== '') {
            if ($type === 'tile' or $type === 'tilewms') {
                $sqlts .= 'AND servername='.$this->db_quote_escape_string($layername).' ';
            }
            else if ($layername !== '') {
                $sqlts .= 'AND (servername=';
                $servers = explode(';;', $layername);
                $qservers = array();
                foreach ($servers as $s) {
                    array_push($qservers, $this->db_quote_escape_string($s));
                }
                $sqlts .= implode(' OR servername=', $qservers);
                $sqlts .= ') ';
            }
            else {
                if ($this->dbtype === 'pgsql'){
                    $sqlts .= 'AND false ';
                }
                else {
                    $sqlts .= 'AND 0 ';
                }
            }
        }
        $sqlts .= 'AND type='.$this->db_quote_escape_string($type).';';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $tss = Array();
        while ($row = $req->fetch()){
            $tss[$row["servername"]] = Array();
            foreach (Array('servername', 'type', 'url', 'layers', 'version', 'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution') as $field) {
                $tss[$row['servername']][$field] = $row[$field];
            }
        }
        $req->closeCursor();
        return $tss;
    }

    /*
     * recursively search files with given extensions (case insensitive)
     */
    private function searchFilesWithExt($folder, $sharedAllowed, $mountedAllowed, $extensions) {
        $res = Array();
        foreach ($folder->getDirectoryListing() as $node) {
            // top level files with matching ext
            if ($node->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                $fext = '.'.strtolower(pathinfo($node->getName(), PATHINFO_EXTENSION));
                if (in_array($fext, $extensions)) {
                    if ($sharedAllowed or !$node->isShared()) {
                        array_push($res, $node);
                    }
                }
            }
            // top level folders
            else {
                if (    ($mountedAllowed or !$node->isMounted())
                    and ($sharedAllowed or !$node->isShared())
                ) {
                    $subres = $this->searchFilesWithExt($node, $sharedAllowed, $mountedAllowed, $extensions);
                    $res = array_merge($res, $subres);
                }
            }
        }
        return $res;
    }

    private function resetTrackDbBy304() {
        $alreadyDone = $this->config->getAppValue('gpxpod', 'reset304');
        if ($alreadyDone !== '1') {
            $sqldel = 'DELETE FROM *PREFIX*gpxpod_tracks ; ';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
            $this->config->setAppValue('gpxpod', 'reset304', '1');
        }
    }

    private function resetPicturesDbBy404() {
        $alreadyDone = $this->config->getAppValue('gpxpod', 'resetPics404');
        if ($alreadyDone !== '1') {
            $sqldel = 'DELETE FROM *PREFIX*gpxpod_pictures ; ';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
            $this->config->setAppValue('gpxpod', 'resetPics404', '1');
        }
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
        $gpxcomp_root_url = 'gpxvcomp';
        $gpxedit_version = $this->config->getAppValue('gpxedit', 'installed_version');
        $gpxmotion_version = $this->config->getAppValue('gpxmotion', 'installed_version');

        $this->cleanDbFromAbsentFiles(null);

        $this->resetTrackDbBy304();
        $this->resetPicturesDbBy404();

        $alldirs = $this->getDirectories($this->userId);

        $gpxelePath = getProgramPath('gpxelevations');
        $hassrtm = False;
        if ($gpxelePath !== null){
            $hassrtm = True;
        }

        $tss = $this->getUserTileServers('tile');
        $oss = $this->getUserTileServers('overlay');
        $tssw = $this->getUserTileServers('tilewms');
        $ossw = $this->getUserTileServers('overlaywms');

        $extraSymbolList = $this->getExtraSymbolList();

        // PARAMS to view

        sort($alldirs);
        require_once('tileservers.php');
        $params = [
            'dirs'=>$alldirs,
            'gpxcomp_root_url'=>$gpxcomp_root_url,
            'username'=>$this->userId,
            'hassrtm'=>$hassrtm,
            'basetileservers'=>$baseTileServers,
            'usertileservers'=>$tss,
            'useroverlayservers'=>$oss,
            'usertileserverswms'=>$tssw,
            'useroverlayserverswms'=>$ossw,
            'publicgpx'=>'',
            'publicmarker'=>'',
            'publicdir'=>'',
            'pictures'=>'',
            'token'=>'',
            'gpxedit_version'=>$gpxedit_version,
            'gpxmotion_version'=>$gpxmotion_version,
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

    private function getDirectoryId($userId, $path) {
        $sql = '
            SELECT id, path
            FROM *PREFIX*gpxpod_directories
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).'
                AND path='.$this->db_quote_escape_string($path).';';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        $id = null;
        while ($row = $req->fetch()){
            $id = $row['id'];
            break;
        }
        $req->closeCursor();

        return $id;
    }

    private function getDirectoryPath($userId, $id) {
        $sql = '
            SELECT id, path
            FROM *PREFIX*gpxpod_directories
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).'
                AND id='.$this->db_quote_escape_string($id).';';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        $path = null;
        while ($row = $req->fetch()){
            $path = $row['path'];
            break;
        }
        $req->closeCursor();

        return $path;
    }

    /**
     * Ajax add directory
     * @NoAdminRequired
     */
    public function addDirectory($path) {
        $userFolder = \OC::$server->getUserFolder();

        $cleanpath = str_replace(array('../', '..\\'), '',  $path);
        if ($userFolder->nodeExists($cleanpath)) {
            if ($this->getDirectoryId($this->userId, $cleanpath) === null) {
                $sql = '
                    INSERT INTO *PREFIX*gpxpod_directories
                    ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', path)
                    VALUES ('.
                        $this->db_quote_escape_string($this->userId).','.
                        $this->db_quote_escape_string($cleanpath).'
                    ) ;';
                $req = $this->dbconnection->prepare($sql);
                $req->execute();
                $req->closeCursor();

                $addedId = $this->getDirectoryId($this->userId, $cleanpath);

                return new DataResponse($addedId);
            }
            else {
                return new DataResponse($cleanpath.' already there', 400);
            }
        }
        else {
            return new DataResponse($cleanpath.' does not exist', 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addDirectoryRecursive($path) {
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();

        $cleanpath = str_replace(array('../', '..\\'), '',  $path);
        if ($userFolder->nodeExists($cleanpath)) {
            $folder = $userFolder->get($cleanpath);

            // DIRS array population
            $optionValues = $this->getSharedMountedOptionValue();
            $sharedAllowed = $optionValues['sharedAllowed'];
            $mountedAllowed = $optionValues['mountedAllowed'];
            $showpicsonlyfold = $this->config->getUserValue($this->userId, 'gpxpod', 'showpicsonlyfold', 'true');
            $searchJpg = ($showpicsonlyfold === 'true');
            $extensions = array_keys($this->extensions);
            if ($searchJpg) {
                $extensions = array_merge($extensions, ['.jpg']);
            }
            $files = $this->searchFilesWithExt($folder, $sharedAllowed, $mountedAllowed, $extensions);
            $alldirs = Array();
            foreach($files as $file) {
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

            // add each directory
            $addedDirs = [];
            foreach ($alldirs as $dir) {
                if ($this->getDirectoryId($this->userId, $dir) === null) {
                    $sql = '
                        INSERT INTO *PREFIX*gpxpod_directories
                        ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', path)
                        VALUES ('.
                            $this->db_quote_escape_string($this->userId).','.
                            $this->db_quote_escape_string($dir).'
                        ) ;';
                    $req = $this->dbconnection->prepare($sql);
                    $req->execute();
                    $req->closeCursor();

                    array_push($addedDirs, $dir);
                }
            }
            return new DataResponse($addedDirs);
        }
        else {
            return new DataResponse($cleanpath.' does not exist', 400);
        }
    }

    /**
     * @NoAdminRequired
     */
    public function delDirectory($path) {
        $sqldel = '
            DELETE FROM *PREFIX*gpxpod_directories
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND path='.$this->db_quote_escape_string($path).' ;';
        $req = $this->dbconnection->prepare($sqldel);
        $req->execute();
        $req->closeCursor();

        // delete track metadata from DB
        $trackpathToDelete = [];
        $sqlmar = '
            SELECT trackpath, marker
            FROM *PREFIX*gpxpod_tracks
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND trackpath LIKE '.$this->db_quote_escape_string($path.'%').'; ';
        $req = $this->dbconnection->prepare($sqlmar);
        $req->execute();
        while ($row = $req->fetch()){
            if (dirname($row['trackpath']) === $path){
                array_push($trackpathToDelete, $row['trackpath']);
            }
        }

        foreach ($trackpathToDelete as $trackpath) {
            $sqldel = '
                DELETE FROM *PREFIX*gpxpod_tracks
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND trackpath='.$this->db_quote_escape_string($trackpath).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
        }

        return new DataResponse('DONE');
    }

    private function getDirectories($userId) {
        $sql = '
            SELECT id, path
            FROM *PREFIX*gpxpod_directories
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).' ;';
        $req = $this->dbconnection->prepare($sql);
        $req->execute();
        $dirs = Array();
        while ($row = $req->fetch()){
            array_push($dirs, $row['path']);
        }
        $req->closeCursor();

        return $dirs;
    }

    /**
     * Ajax gpx retrieval
     * @NoAdminRequired
     */
    public function getgpx($path) {
        $userFolder = \OC::$server->getUserFolder();

        $cleanpath = str_replace(array('../', '..\\'), '',  $path);
        $gpxContent = '';
        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE){
                if (endswith($file->getName(), '.GPX') or endswith($file->getName(), '.gpx')){
                    $gpxContent = remove_utf8_bom($file->getContent());
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
    public function getpublicgpx($path, $username) {
        $userFolder = \OC::$server->getUserFolder($username);

        $cleanpath = str_replace(array('../', '..\\'), '',  $path);
        $gpxContent = '';
        if ($userFolder->nodeExists($cleanpath)){
            $file = $userFolder->get($cleanpath);

            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE){
                if (endswith($file->getName(), '.GPX') or endswith($file->getName(), '.gpx')){
                    // we check the file is actually shared by public link
                    $dl_url = $this->getPublinkDownloadURL($file, $username);

                    if ($dl_url !== null){
                        $gpxContent = remove_utf8_bom($file->getContent());
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
    private function getMarkerFromFile($file) {
        $DISTANCE_BETWEEN_SHORT_POINTS = 300;
        $STOPPED_SPEED_THRESHOLD = 0.9;

        $name = $file->getName();

        // get path relative to user '/'
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();
        $dirname = dirname($file->getPath());
        $gpx_relative_dir = str_replace($userfolder_path, '', $dirname);
        if ($gpx_relative_dir !== '') {
            $gpx_relative_dir = rtrim($gpx_relative_dir, '/');
            $gpx_relative_dir = str_replace('//', '/', $gpx_relative_dir);
        }
        else {
            $gpx_relative_dir = '/';
        }

        $gpx_content = $file->getContent();

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
        $linkurl = '';
        $linktext = '';

        $isGoingUp = False;
        $lastDeniv = null;
        $upBegin = null;
        $downBegin = null;
        $lastTime = null;

        try{
            $gpx = new \SimpleXMLElement($gpx_content);
        }
        catch (\Exception $e) {
            $this->logger->error(
                "Exception in ".$name." gpx parsing : ".$e->getMessage(),
                array('app' => $this->appName)
            );
            return null;
        }

        if (count($gpx->trk) === 0 and count($gpx->rte) === 0 and count($gpx->wpt) === 0){
            $this->logger->error(
                'Nothing to parse in '.$name.' gpx file',
                array('app' => $this->appName)
            );
            return null;
        }

        // METADATA
        if (!empty($gpx->metadata) and !empty($gpx->metadata->link)) {
            $linkurl = $gpx->metadata->link['href'];
            if (!empty($gpx->metadata->link->text)) {
                $linktext = $gpx->metadata->link->text;
            }
        }

        // TRACKS
        foreach ($gpx->trk as $track) {
            $trackname = str_replace("\n", '', $track->name);
            if (empty($trackname)) {
                $trackname = '';
            }
            $trackname = str_replace('"', "'", $trackname);
            $trackNameList .= sprintf('"%s",', $trackname);
            foreach ($track->trkseg as $segment) {
                $lastPoint = null;
                $lastTime = null;
                $pointIndex = 0;
                $lastDeniv = null;
                foreach ($segment->trkpt as $point) {
                    if (empty($point['lat']) or empty($point['lon'])) {
                        continue;
                    }
                    if (empty($point->ele)) {
                        $pointele = null;
                    }
                    else{
                        $pointele = floatval($point->ele);
                    }
                    if (empty($point->time)) {
                        $pointtime = null;
                    }
                    else{
                        $pointtime = new \DateTime($point->time);
                    }
                    if ($lastPoint !== null and (!empty($lastPoint->ele))){
                        $lastPointele = floatval($lastPoint->ele);
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
                    $pointlat = floatval($point['lat']);
                    $pointlon = floatval($point['lon']);
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
                        $deniv = $pointele - floatval($lastPoint->ele);
                    }
                    if ($lastDeniv !== null and $pointele !== null and $lastPoint !== null and (!empty($lastPoint->ele))){
                        // we start to go up
                        if ($isGoingUp === False and $deniv > 0){
                            $upBegin = floatval($lastPoint->ele);
                            $isGoingUp = True;
                            $neg_elevation += ($downBegin - floatval($lastPoint->ele));
                        }
                        if ($isGoingUp === True and $deniv < 0){
                            // we add the up portion
                            $pos_elevation += (floatval($lastPointele) - $upBegin);
                            $isGoingUp = False;
                            $downBegin = floatval($lastPoint->ele);
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

        }

        # ROUTES
        foreach($gpx->rte as $route){
            $routename = str_replace("\n", '', $route->name);
            if (empty($routename)){
                $routename = '';
            }
            $routename = str_replace('"', "'", $routename);
            $trackNameList .= sprintf('"%s",', $routename);

            $lastPoint = null;
            $lastTime = null;
            $pointIndex = 0;
            $lastDeniv = null;
            foreach($route->rtept as $point){
                if (empty($point['lat']) or empty($point['lon'])) {
                    continue;
                }
                if (empty($point->ele)){
                    $pointele = null;
                }
                else{
                    $pointele = floatval($point->ele);
                }
                if (empty($point->time)){
                    $pointtime = null;
                }
                else{
                    $pointtime = new \DateTime($point->time);
                }
                if ($lastPoint !== null and (!empty($lastPoint->ele))){
                    $lastPointele = floatval($lastPoint->ele);
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
                $pointlat = floatval($point['lat']);
                $pointlon = floatval($point['lon']);
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
                    $deniv = $pointele - floatval($lastPoint->ele);
                }
                if ($lastDeniv !== null and $pointele !== null and $lastPoint !== null and (!empty($lastPoint->ele))){
                    // we start to go up
                    if ($isGoingUp === False and $deniv > 0){
                        $upBegin = floatval($lastPoint->ele);
                        $isGoingUp = True;
                        $neg_elevation += ($downBegin - floatval($lastPoint->ele));
                    }
                    if ($isGoingUp === True and $deniv < 0){
                        // we add the up portion
                        $pos_elevation += (floatval($lastPointele) - $upBegin);
                        $isGoingUp = False;
                        $downBegin = floatval($lastPoint->ele);
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
        $moving_pace = 0;
        if ($moving_time > 0){
            $moving_avg_speed = $total_distance / $moving_time;
            $moving_avg_speed = $moving_avg_speed / 1000;
            $moving_avg_speed = $moving_avg_speed * 3600;
            $moving_avg_speed = sprintf('%.2f', $moving_avg_speed);
            // pace in minutes/km
            $moving_pace = $moving_time / $total_distance;
            $moving_pace = $moving_pace / 60;
            $moving_pace = $moving_pace * 1000;
            $moving_pace = sprintf('%.2f', $moving_pace);
        }

        # WAYPOINTS
        foreach($gpx->wpt as $waypoint){
            array_push($shortPointList, Array($waypoint['lat'], $waypoint['lon']));

            $waypointlat = floatval($waypoint['lat']);
            $waypointlon = floatval($waypoint['lon']);

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
            $shortPointListTxt .= sprintf('[%f, %f],', $sp[0], $sp[1]);
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

        $result = sprintf('[%s, %s, "%s", "%s", %.3f, "%s", "%s", "%s", %s, %.2f, %s, %s, %s, %.2f, "%s", "%s", %s, %d, %d, %d, %d, %s, %s, "%s", "%s", %.2f]',
            $lat,
            $lon,
            \encodeURIComponent($gpx_relative_dir),
            \encodeURIComponent($name),
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
            $trackNameList,
            str_replace('"', "'", $linkurl),
            str_replace('"', "'", $linktext),
            $moving_pace
        );
        return $result;
    }

    /*
     * get marker string for each gpx file
     * return an array indexed by trackname
     */
    private function getMarkersFromFiles($gpxs_to_process) {
        $result = Array();
        foreach ($gpxs_to_process as $gpxfile){
            $markerJson = $this->getMarkerFromFile($gpxfile);
            if ($markerJson !== null){
                $result[$gpxfile->getPath()] = $markerJson;
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
     * Then process the files to produce marker content.
     * Then INSERT or UPDATE the database with processed data.
     * Then get the markers for all gpx files in the target folder
     * Then clean useless database entries (for files that no longer exist)
     *
     * @NoAdminRequired
     */
    public function getmarkers($subfolder, $processAll, $recursive='0') {
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();
        $recursive = ($recursive !== '0');

        if ($subfolder === null or !$userFolder->nodeExists($subfolder) or $this->getDirectoryId($this->userId, $subfolder) === null) {
            return new DataResponse('No such directory', 400);
        }

        $subfolder_path = $userFolder->get($subfolder)->getPath();

        $optionValues = $this->getSharedMountedOptionValue();
        $sharedAllowed = $optionValues['sharedAllowed'];
        $mountedAllowed = $optionValues['mountedAllowed'];

        // Convert KML to GPX
        // only if we want to display a folder AND it exists AND we want
        // to compute AND we find GPSBABEL AND file was not already converted

        if ($subfolder === '/'){
            $subfolder = '';
        }

        $filesByExtension = Array();
        foreach($this->extensions as $ext => $gpsbabel_fmt){
            $filesByExtension[$ext] = Array();
        }

        if (!$recursive) {
            foreach ($userFolder->get($subfolder)->getDirectoryListing() as $ff){
                if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                    $ffext = '.'.strtolower(pathinfo($ff->getName(), PATHINFO_EXTENSION));
                    if (in_array( $ffext, array_keys($this->extensions))) {
                        // if shared files are allowed or it is not shared
                        if ($sharedAllowed or !$ff->isShared()) {
                            array_push($filesByExtension[$ffext], $ff);
                        }
                    }
                }
            }
        }
        else {
            $showpicsonlyfold = $this->config->getUserValue($this->userId, 'gpxpod', 'showpicsonlyfold', 'true');
            $searchJpg = ($showpicsonlyfold === 'true');
            $extensions = array_keys($this->extensions);
            if ($searchJpg) {
                $extensions = array_merge($extensions, ['.jpg']);
            }
            $files = $this->searchFilesWithExt($userFolder->get($subfolder), $sharedAllowed, $mountedAllowed, $extensions);
            foreach ($files as $file) {
                $fileext = '.'.strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
                if ($sharedAllowed or !$file->isShared()) {
                    array_push($filesByExtension[$fileext], $file);
                }
            }
        }

        // convert kml, tcx etc...
        if (    $userFolder->nodeExists($subfolder)
            and $userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {

            $gpsbabel_path = getProgramPath('gpsbabel');
            $igctrack = $this->config->getUserValue($this->userId, 'gpxpod', 'igctrack');

            if ($gpsbabel_path !== null){
                foreach($this->extensions as $ext => $gpsbabel_fmt) {
                    if ($ext !== '.gpx' and $ext !== '.jpg') {
                        $igcfilter1 = '';
                        $igcfilter2 = '';
                        if ($ext === '.igc') {
                            if ($igctrack === 'pres') {
                                $igcfilter1 = '-x';
                                $igcfilter2 = 'track,name=PRESALTTRK';
                            }
                            else if ($igctrack === 'gnss') {
                                $igcfilter1 = '-x';
                                $igcfilter2 = 'track,name=GNSSALTTRK';
                            }
                        }
                        foreach($filesByExtension[$ext] as $f) {
                            $name = $f->getName();
                            $gpx_targetname = str_replace($ext, '.gpx', $name);
                            $gpx_targetname = str_replace(strtoupper($ext), '.gpx', $gpx_targetname);
                            $gpx_targetfolder = $f->getParent();
                            if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
                                // we read content, then launch the command, then write content on stdin
                                // then read gpsbabel stdout then write it in a NC file
                                $content = $f->getContent();

                                if ($igcfilter1 !== '') {
                                    $args = Array('-i', $gpsbabel_fmt, '-f', '-',
                                        $igcfilter1, $igcfilter2, '-o',
                                        'gpx', '-F', '-');
                                }
                                else {
                                    $args = Array('-i', $gpsbabel_fmt, '-f', '-',
                                        '-o', 'gpx', '-F', '-');
                                }
                                $cmdparams = '';
                                foreach($args as $arg){
                                    $shella = escapeshellarg($arg);
                                    $cmdparams .= " $shella";
                                }
                                $descriptorspec = array(
                                    0 => array("pipe", "r"),
                                    1 => array("pipe", "w"),
                                    2 => array("pipe", "w")
                                );
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
                            }
                        }
                    }
                }
            }
            else {
                // Fallback for igc without GpsBabel
                foreach ($filesByExtension['.igc'] as $f) {
                    $name = $f->getName();
                    $gpx_targetname = str_replace(['.igc', '.IGC'], '.gpx', $name);
                    $gpx_targetfolder = $f->getParent();
                    if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
                        $fdesc = $f->fopen('r');
                        $gpx_clear_content = igcToGpx($fdesc, $igctrack);
                        fclose($fdesc);
                        $gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
                        $gpx_file->putContent($gpx_clear_content);
                    }
                }
                // Fallback KML conversion without GpsBabel
                foreach($filesByExtension['.kml'] as $f) {
                    $name = $f->getName();
                    $gpx_targetname = str_replace(['.kml', '.KML'], '.gpx', $name);
                    $gpx_targetfolder = $f->getParent();
                    if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
                        $content = $f->getContent();
                        $gpx_clear_content = kmlToGpx($content);
                        $gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
                        $gpx_file->putContent($gpx_clear_content);
                    }
                }
                // Fallback TCX conversion without GpsBabel
                foreach($filesByExtension['.tcx'] as $f) {
                    $name = $f->getName();
                    $gpx_targetname = str_replace(['.tcx', '.TCX'], '.gpx', $name);
                    $gpx_targetfolder = $f->getParent();
                    if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
                        $content = $f->getContent();
                        $gpx_clear_content = tcxToGpx($content);
                        $gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
                        $gpx_file->putContent($gpx_clear_content);
                    }
                }
            }
        }

        // PROCESS gpx files and fill DB

        if ($userFolder->nodeExists($subfolder) and
            $userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {

            // find gpxs db style
            $sqlgpx = '
                SELECT trackpath, contenthash
                FROM *PREFIX*gpxpod_tracks
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
            $req = $this->dbconnection->prepare($sqlgpx);
            $req->execute();
            $gpxs_in_db = Array();
            while ($row = $req->fetch()){
                $gpxs_in_db[$row['trackpath']] = $row['contenthash'];
            }
            $req->closeCursor();


            // find gpxs
            $gpxfiles = Array();

            if (!$recursive) {
                foreach ($userFolder->get($subfolder)->getDirectoryListing() as $ff){
                    if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
                        $ffext = '.'.strtolower(pathinfo($ff->getName(), PATHINFO_EXTENSION));
                        if ($ffext === '.gpx') {
                            // if shared files are allowed or it is not shared
                            if ($sharedAllowed or !$ff->isShared()) {
                                array_push($gpxfiles, $ff);
                            }
                        }
                    }
                }
            }
            else {
                $gpxfiles = $this->searchFilesWithExt($userFolder->get($subfolder), $sharedAllowed, $mountedAllowed, ['.gpx']);
            }

            // CHECK what is to be processed
            $gpxs_to_process = Array();
            $newCRC = Array();
            foreach ($gpxfiles as $gg){
                $gpx_relative_path = str_replace($userfolder_path, '', $gg->getPath());
                $gpx_relative_path = rtrim($gpx_relative_path, '/');
                $gpx_relative_path = str_replace('//', '/', $gpx_relative_path);
                $newCRC[$gpx_relative_path] = $gg->getMTime().'.'.$gg->getSize();
                // if the file is not in the DB or if its content hash has changed
                if ((! array_key_exists($gpx_relative_path, $gpxs_in_db)) or
                     $gpxs_in_db[$gpx_relative_path] !== $newCRC[$gpx_relative_path] or
                     $processAll === 'true'
                ){
                    // not in DB or hash changed
                    array_push($gpxs_to_process, $gg);
                }
            }

            $markers = $this->getMarkersFromFiles($gpxs_to_process);

            // DB STYLE
            foreach ($markers as $trackpath => $marker){
                $gpx_relative_path = str_replace($userfolder_path, '', $trackpath);
                $gpx_relative_path = rtrim($gpx_relative_path, '/');
                $gpx_relative_path = str_replace('//', '/', $gpx_relative_path);

                if (! array_key_exists($gpx_relative_path, $gpxs_in_db)){
                    $sql = '
                        INSERT INTO *PREFIX*gpxpod_tracks
                        ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', trackpath, contenthash, marker)
                        VALUES ('.
                            $this->db_quote_escape_string($this->userId).','.
                            $this->db_quote_escape_string($gpx_relative_path).','.
                            $this->db_quote_escape_string($newCRC[$gpx_relative_path]).','.
                            $this->db_quote_escape_string($marker).'
                        ) ;';
                    $req = $this->dbconnection->prepare($sql);
                    $req->execute();
                    $req->closeCursor();
                }
                else{
                    $sqlupd = '
                        UPDATE *PREFIX*gpxpod_tracks
                        SET marker='.$this->db_quote_escape_string($marker).',
                            contenthash='.$this->db_quote_escape_string($newCRC[$gpx_relative_path]).'
                        WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                              AND trackpath='.$this->db_quote_escape_string($gpx_relative_path).' ;';
                    $req = $this->dbconnection->prepare($sqlupd);
                    $req->execute();
                    $req->closeCursor();
                }
            }
        }

        // PROCESS error management

        // info for JS

        // build markers
        $subfolder_sql = $subfolder;
        if ($subfolder === ''){
            $subfolder_sql = '/';
        }
        $markertxt = '{"markers" : {';
        // DB style
        $sqlmar = '
            SELECT id, trackpath, marker
            FROM *PREFIX*gpxpod_tracks
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND trackpath LIKE '.$this->db_quote_escape_string($subfolder_sql.'%').'; ';
        $req = $this->dbconnection->prepare($sqlmar);
        $req->execute();
        while ($row = $req->fetch()) {
            if ($recursive or dirname($row['trackpath']) === $subfolder_sql) {
                // if the gpx file exists
                if ($userFolder->nodeExists($row['trackpath'])) {
                    $ff = $userFolder->get($row['trackpath']);
                    // if it's a file, if shared files are allowed or it's not shared
                    if (    $ff->getType() === \OCP\Files\FileInfo::TYPE_FILE
                        and ($sharedAllowed or !$ff->isShared())
                    ){
                        $markertxt .= '"'.$row['id'] . '": ' . $row['marker'];
                        $markertxt .= ',';
                    }
                }
            }
        }
        $req->closeCursor();

        // CLEANUP DB for non-existing files
        $this->cleanDbFromAbsentFiles($subfolder);

        $markertxt = rtrim($markertxt, ',');
        $markertxt .= '}}';

        $pictures_json_txt = $this->getGeoPicsFromFolder($subfolder, $recursive);

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
    public function processTrackElevations($path, $smooth) {
        $userFolder = \OC::$server->getUserFolder();
        $gpxelePath = getProgramPath('gpxelevations');
        $success = False;
        $message = '';

        $filerelpath = $path;
        $folderPath = dirname($path);

        if ($userFolder->nodeExists($filerelpath) and
            $userFolder->get($filerelpath)->getType() === \OCP\Files\FileInfo::TYPE_FILE and
            $gpxelePath !== null
        ){
            // srtmification
            $gpxfile = $userFolder->get($filerelpath);
            $gpxfilename = $gpxfile->getName();
            $gpxcontent = $gpxfile->getContent();

            $osmooth = '';
            if ($smooth === 'true'){
                $osmooth = '-s';
            }

            // tricky, isn't it ? as gpxelevations wants to read AND write in files,
            // we use BASH process substitution to make it read from STDIN
            // and write to cat which writes to STDOUT, then we filter to only keep what we want and VOILA
            $cmd = 'bash -c "export HOMEPATH=\''.$this->gpxpodCachePath.'\' ; export HOME=\''.$this->gpxpodCachePath.'\' ; '.$gpxelePath.' <(cat -) '.$osmooth.' -o -f >(cat -) 1>&2 "';

            $descriptorspec = array(
                0 => array("pipe", "r"),
                1 => array("pipe", "w"),
                2 => array("pipe", "w")
            );
            // srtm.py (used by gpxelevations) needs HOME or HOMEPATH
            // to be set to store cache data
            $process = proc_open(
                $cmd,
                $descriptorspec,
                $pipes
            );
            // write to stdin
            fwrite($pipes[0], $gpxcontent);
            fclose($pipes[0]);
            // read from stdout
            $res_content = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            // read from stderr
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $return_value = proc_close($process);

            $subfolderobj = $userFolder->get($folderPath);
            // overwrite original gpx files with corrected ones
            if ($return_value === 0){
                $correctedName = str_replace(Array('.gpx', '.GPX'), '_corrected.gpx', $gpxfilename);
                if ($subfolderobj->nodeExists($correctedName)){
                    $of = $subfolderobj->get($correctedName);
                    if ($of->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                        $of->isUpdateable()){
                        $of->putContent($res_content);
                    }
                }
                else{
                    if ($subfolderobj->getType() === \OCP\Files\FileInfo::TYPE_FOLDER and
                        $subfolderobj->isCreatable()){
                        $subfolderobj->newFile($correctedName);
                        $subfolderobj->get($correctedName)->putContent($res_content);
                    }
                }
            }
            else{
                $message = $this->trans->t('There was an error during "gpxelevations" execution on the server');
                $this->logger->error('There was an error during "gpxelevations" execution on the server : '. $stderr, array('app' => $this->appName));
            }

            // PROCESS

            if ($return_value === 0){
                $mar_content = $this->getMarkerFromFile($subfolderobj->get($correctedName));
            }

            $cleanFolder = $folderPath;
            if ($folderPath === '/'){
                $cleanFolder = '';
            }
            // in case it does not exists, the following query won't have any effect
            if ($return_value === 0){
                $gpx_relative_path = $cleanFolder.'/'.$correctedName;
                $sqlupd = '
                    UPDATE *PREFIX*gpxpod_tracks
                    SET marker='.$this->db_quote_escape_string($mar_content).'
                    WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                          AND trackpath='.$this->db_quote_escape_string($gpx_relative_path).' ;';
                $req = $this->dbconnection->prepare($sqlupd);
                $req->execute();
                $req->closeCursor();
                $success = True;
            }
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

    private function getSharedMountedOptionValue($uid=null){
        $userId = $uid;
        if ($uid === null) {
            $userId = $this->userId;
        }
        // get option values
        $ss = $this->config->getUserValue($userId, 'gpxpod', 'showshared', 'true');
        $sm = $this->config->getUserValue($userId, 'gpxpod', 'showmounted', 'true');
        $sharedAllowed = ($ss === 'true');
        $mountedAllowed = ($sm === 'true');
        return ['sharedAllowed'=>$sharedAllowed, 'mountedAllowed'=>$mountedAllowed];
    }

    /**
     * get list of geolocated pictures in $subfolder with coordinates
     * first copy the pics to a temp dir
     * then get the pic list and coords with gpsbabel
     */
    private function getGeoPicsFromFolder($subfolder, $recursive, $user=null){
        $pictures_json_txt = '{';

        $userId = $user;
        // if user is not given, the request comes from connected user threw getmarkers
        if ($user === null){
            $userFolder = \OC::$server->getUserFolder();
            $userId = $this->userId;
        }
        // else, it comes from a public dir
        else{
            $userFolder = \OC::$server->getUserFolder($user);
        }
        $subfolder = str_replace(array('../', '..\\'), '', $subfolder);
        $subfolder_path = $userFolder->get($subfolder)->getPath();
        $userfolder_path = $userFolder->getPath();

        $imagickAvailable = class_exists('Imagick');

        $optionValues = $this->getSharedMountedOptionValue($user);
        $sharedAllowed = $optionValues['sharedAllowed'];
        $mountedAllowed = $optionValues['mountedAllowed'];

        // get picture files
        $picfiles = [];
        if ($recursive) {
            $picfiles = $this->searchFilesWithExt($userFolder->get($subfolder), $sharedAllowed, $mountedAllowed, ['.jpg']);
        }
        else {
            foreach ($userFolder->get($subfolder)->search('.jpg') as $picfile){
                if ($picfile->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                    dirname($picfile->getPath()) === $subfolder_path and
                    (
                        endswith($picfile->getName(), '.jpg') or
                        endswith($picfile->getName(), '.JPG')
                    )
                ){
                    array_push($picfiles, $picfile);
                }
            }
        }
        // get list of paths to manage deletion of absent files
        $picpaths = [];
        foreach ($picfiles as $picfile) {
            $pic_relative_path = str_replace($userfolder_path, '', $picfile->getPath());
            $pic_relative_path = rtrim($pic_relative_path, '/');
            $pic_relative_path = str_replace('//', '/', $pic_relative_path);
            array_push($picpaths, $pic_relative_path);
        }

        $dbToDelete = [];
        // get what's in the DB
        $dbPicsWithCoords = [];
        $sqlgpx = '
                SELECT path, contenthash
                FROM *PREFIX*gpxpod_pictures
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).'
                    AND lat IS NOT NULL
                    AND path LIKE '.$this->db_quote_escape_string($subfolder.'%').' ;';
        $req = $this->dbconnection->prepare($sqlgpx);
        $req->execute();
        $gpxs_in_db = Array();
        while ($row = $req->fetch()){
            $dbPicsWithCoords[$row['path']] = $row['contenthash'];
            if (! in_array($row['path'], $picpaths)) {
                array_push($dbToDelete, $row['path']);
            }
        }
        $req->closeCursor();

        // get non-geotagged pictures
        $dbPicsWithoutCoords = [];
        $sqlgpx = '
                SELECT path, contenthash
                FROM *PREFIX*gpxpod_pictures
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).'
                    AND lat IS NULL
                    AND path LIKE '.$this->db_quote_escape_string($subfolder.'%').' ;';
        $req = $this->dbconnection->prepare($sqlgpx);
        $req->execute();
        $gpxs_in_db = Array();
        while ($row = $req->fetch()){
            $dbPicsWithoutCoords[$row['path']] = $row['contenthash'];
            if (! in_array($row['path'], $picpaths)) {
                array_push($dbToDelete, $row['path']);
            }
        }
        $req->closeCursor();

        // CHECK what is to be processed
        $picfilesToProcess = [];
        $newCRC = [];
        foreach ($picfiles as $pp){
            $pic_relative_path = str_replace($userfolder_path, '', $pp->getPath());
            $pic_relative_path = rtrim($pic_relative_path, '/');
            $pic_relative_path = str_replace('//', '/', $pic_relative_path);
            $newCRC[$pic_relative_path] = $pp->getMTime().'.'.$pp->getSize();
            // if the file is not in the DB or if its content hash has changed
            if ((! array_key_exists($pic_relative_path, $dbPicsWithCoords))
                and (! array_key_exists($pic_relative_path, $dbPicsWithoutCoords))
            ) {
                array_push($picfilesToProcess, $pp);
            }
            else if (array_key_exists($pic_relative_path, $dbPicsWithCoords)
                     and $dbPicsWithCoords[$pic_relative_path] !== $newCRC[$pic_relative_path]
            ) {
                array_push($picfilesToProcess, $pp);
            }
            else if (array_key_exists($pic_relative_path, $dbPicsWithoutCoords)
                     and $dbPicsWithoutCoords[$pic_relative_path] !== $newCRC[$pic_relative_path]
            ) {
                array_push($picfilesToProcess, $pp);
            }
            else {
                if (array_key_exists($pic_relative_path, $dbPicsWithoutCoords)) {
                    error_log('NOOOOT '.$pic_relative_path);
                }
            }
        }

        // get coordinates of each picture file
        foreach ($picfilesToProcess as $picfile) {
            try {
                $lat = null;
                $lon = null;
                $dateTaken = null;

                // we try with imagick if available
                if ($imagickAvailable) {
                    $pfile = $picfile->fopen('r');
                    $img = new \Imagick();
                    $img->readImageFile($pfile);
                    $allGpsProp = $img->getImageProperties('exif:GPS*');
                    if (    isset($allGpsProp['exif:GPSLatitude'])
                        and isset($allGpsProp['exif:GPSLongitude'])
                        and isset($allGpsProp['exif:GPSLatitudeRef'])
                        and isset($allGpsProp['exif:GPSLongitudeRef'])
                    ) {
                        $lon = getDecimalCoords(explode(', ', $allGpsProp['exif:GPSLongitude']), $allGpsProp['exif:GPSLongitudeRef']);
                        $lat = getDecimalCoords(explode(', ', $allGpsProp['exif:GPSLatitude']), $allGpsProp['exif:GPSLatitudeRef']);
                    }
                    $dateProp = $img->getImageProperties('exif:DateTimeOriginal');
                    if (isset($dateProp['exif:DateTimeOriginal'])) {
                        $dateTaken = strtotime($dateProp['exif:DateTimeOriginal']);
                    }
                    fclose($pfile);
                }
                // if imagick is not available, we try with php exif function
                else {
                    //$imageString = $picfile->getContent();
                    //$exif = \exif_read_data("data://image/jpeg;base64," . base64_encode($imageString), 0, true);
                    $filePath = $picfile->getStorage()->getLocalFile($picfile->getInternalPath());
                    $exif = @exif_read_data($filePath, null, true);
                    if (    isset($exif['GPS'])
                        and isset($exif['GPS']['GPSLongitude'])
                        and isset($exif['GPS']['GPSLatitude'])
                        and isset($exif['GPS']['GPSLatitudeRef'])
                        and isset($exif['GPS']['GPSLongitudeRef'])
                    ){
                        $lon = getDecimalCoords($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
                        $lat = getDecimalCoords($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
                    }
                    //var_dump($exif);
                    if (isset($exif['EXIF']) and isset($exif['EXIF']['DateTimeOriginal'])) {
                        $dateTaken = strtotime($exif['EXIF']['DateTimeOriginal']);
                    }
                }

                // insert/update the DB
                $pic_relative_path = str_replace($userfolder_path, '', $picfile->getPath());
                $pic_relative_path = rtrim($pic_relative_path, '/');
                $pic_relative_path = str_replace('//', '/', $pic_relative_path);

                if (! array_key_exists($pic_relative_path, $dbPicsWithCoords)
                    and ! array_key_exists($pic_relative_path, $dbPicsWithoutCoords)
                ){
                    $sql = '
                        INSERT INTO *PREFIX*gpxpod_pictures
                        ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', path, contenthash, lat, lon, dateTaken)
                        VALUES ('.
                            $this->db_quote_escape_string($userId).','.
                            $this->db_quote_escape_string($pic_relative_path).','.
                            $this->db_quote_escape_string($newCRC[$pic_relative_path]).','.
                            (($lat === null) ? 'NULL' : $this->db_quote_escape_string($lat)).','.
                            (($lon === null) ? 'NULL' : $this->db_quote_escape_string($lon)).','.
                            (($dateTaken === null) ? 'NULL' : $this->db_quote_escape_string($dateTaken)).'
                        ) ;';
                    $req = $this->dbconnection->prepare($sql);
                    $req->execute();
                    $req->closeCursor();
                }
                else {
                    $sqlupd = '
                        UPDATE *PREFIX*gpxpod_pictures
                        SET lat='.(($lat === null) ? 'NULL' : $this->db_quote_escape_string($lat)).',
                            lon='.(($lon === null) ? 'NULL' : $this->db_quote_escape_string($lon)).',
                            dateTaken='.(($dateTaken === null) ? 'NULL' : $this->db_quote_escape_string($dateTaken)).',
                            contenthash='.$this->db_quote_escape_string($newCRC[$pic_relative_path]).'
                        WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).'
                                AND path='.$this->db_quote_escape_string($pic_relative_path).' ;';
                    $req = $this->dbconnection->prepare($sqlupd);
                    $req->execute();
                    $req->closeCursor();
                }
            }
            catch (\Exception $e) {
                $this->logger->error(
                    'Exception in picture geolocation reading for file '.$picfile->getPath().' : '. $e->getMessage(),
                    array('app' => $this->appName)
                );
            }
        }

        // build result data from DB TODO
        $subfolder_sql = $subfolder;
        if ($subfolder === ''){
            $subfolder_sql = '/';
        }
        $sqlpic = '
            SELECT path, lat, lon, dateTaken
            FROM *PREFIX*gpxpod_pictures
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).'
                  AND lat IS NOT NULL
                  AND lon IS NOT NULL
                  AND path LIKE '.$this->db_quote_escape_string($subfolder_sql.'%').'; ';
        $req = $this->dbconnection->prepare($sqlpic);
        $req->execute();
        while ($row = $req->fetch()) {
            if ($recursive or dirname($row['path']) === $subfolder_sql) {
                // if the pic file exists
                if ($userFolder->nodeExists($row['path'])) {
                    $ff = $userFolder->get($row['path']);
                    // if it's a file, if shared files are allowed or it's not shared
                    if (    $ff->getType() === \OCP\Files\FileInfo::TYPE_FILE
                        and ($sharedAllowed or !$ff->isShared())
                    ){
                        $fileId = $ff->getId();
                        $pictures_json_txt .= '"'. \encodeURIComponent($row['path']).'": ['.$row['lat'].', '.
                                              $row['lon'].', '.$fileId.', '.($row['dateTaken'] ?? 0).'],';
                    }
                }
            }
        }
        $req->closeCursor();

        $pictures_json_txt = rtrim($pictures_json_txt, ',').'}';

        // delete absent files
        foreach ($dbToDelete as $path) {
            $sqldel = '
                DELETE FROM *PREFIX*gpxpod_pictures
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($userId).'
                      AND path='.$this->db_quote_escape_string($path).' ;';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
        }

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

        $sqlmar = '
            SELECT trackpath
            FROM *PREFIX*gpxpod_tracks
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
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
            $sqldel = '
                DELETE FROM *PREFIX*gpxpod_tracks
                WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                      AND (trackpath='.implode(' OR trackpath=', $gpx_paths_to_del).') ;';
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
                            $dl_url = $token.'/download?path=' . rtrim(dirname($subpath), '/');
                            $dl_url .= '&files=' . encodeURIComponent(basename($subpath));

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
                    $sqlgeomar = '
                        SELECT marker
                        FROM *PREFIX*gpxpod_tracks
                        WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).'
                              AND trackpath='.$this->db_quote_escape_string($path).' ;';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $markercontent = $row['marker'];
                        break;
                    }
                    $req->closeCursor();

                    $gpxContent = remove_utf8_bom($thefile->getContent());

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

        // PARAMS to send to template

        require_once('tileservers.php');
        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>'',
            'hassrtm'=>false,
            'basetileservers'=>$baseTileServers,
            'usertileservers'=>Array(),
            'useroverlayservers'=>Array(),
            'usertileserverswms'=>Array(),
            'useroverlayserverswms'=>Array(),
            'publicgpx'=>$gpxContent,
            'publicmarker'=>$markercontent,
            'publicdir'=>'',
            'pictures'=>'',
            'token'=>$dl_url,
            'extrasymbols'=>$extraSymbolList,
            'gpxedit_version'=>'',
            'gpxmotion_version'=>'',
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $response->setHeaders(Array('X-Frame-Options'=>''));
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
                $dl_url = $token.'/download?path=' . encodeURIComponent($dlpath);
                $dl_url .= '&files=' . encodeURIComponent($filename);
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
                    if ($shareNode->nodeExists($path . '/' . $filename)){
                        $theid = $shareNode->get($path . '/' . $filename)->getId();
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

                    $sqlgeomar = '
                        SELECT marker
                        FROM *PREFIX*gpxpod_tracks
                        WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).'
                              AND trackpath='.$this->db_quote_escape_string($rel_file_path).' ;';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $markercontent = $row['marker'];
                        break;
                    }
                    $req->closeCursor();

                    $gpxContent = remove_utf8_bom($thefile->getContent());

                }
                else{
                    return 'This file is not a public share';
                }
            }
            else{
                return 'This file is not a public share';
            }
        }

        $tss = $this->getUserTileServers('tile', $user, $_GET['layer']);
        $tssw = $this->getUserTileServers('tilewms', $user, $_GET['layer']);
        $oss = $this->getUserTileServers('overlay', $user, $_GET['overlay']);
        $ossw = $this->getUserTileServers('overlaywms', $user, $_GET['overlay']);

        $extraSymbolList = $this->getExtraSymbolList();

        // PARAMS to send to template

        require_once('tileservers.php');
        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>'',
            'hassrtm'=>false,
            'basetileservers'=>$baseTileServers,
            'usertileservers'=>$tss,
            'useroverlayservers'=>$oss,
            'usertileserverswms'=>$tssw,
            'useroverlayserverswms'=>$ossw,
            'publicgpx'=>$gpxContent,
            'publicmarker'=>$markercontent,
            'publicdir'=>'',
            'pictures'=>'',
            'token'=>$dl_url,
            'extrasymbols'=>$extraSymbolList,
            'gpxedit_version'=>'',
            'gpxmotion_version'=>'',
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $response->setHeaders(Array('X-Frame-Options'=>''));
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
                                $dl_url = $token . '?path=' . rtrim($subpath, '/');

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
                    $sqlgeomar = '
                        SELECT id, trackpath, marker
                        FROM *PREFIX*gpxpod_tracks
                        WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).'
                              AND (trackpath='.implode(' OR trackpath=', $gpx_inside_thedir).') ;';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    $markertxt = '{"markers" : {';
                    while ($row = $req->fetch()){
                        $trackname = basename($row['trackpath']);
                        $markertxt .= '"'.$row['id'].'": '.$row['marker'];
                        $markertxt .= ',';
                    }
                    $req->closeCursor();

                    $markertxt = rtrim($markertxt, ',');
                    $markertxt .= '}}';
                }
                else{
                    return "This directory is not a public share";
                }
            }
            else{
                return "This directory is not a public share";
            }
            $pictures_json_txt = $this->getGeoPicsFromFolder($path, false, $user);
        }

        $extraSymbolList = $this->getExtraSymbolList();

        // PARAMS to send to template

        $rel_dir_path = str_replace($userfolder_path, '', $thedir->getPath());

        require_once('tileservers.php');
        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>$user,
            'hassrtm'=>false,
            'basetileservers'=>$baseTileServers,
            'usertileservers'=>Array(),
            'useroverlayservers'=>Array(),
            'usertileserverswms'=>Array(),
            'useroverlayserverswms'=>Array(),
            'publicgpx'=>'',
            'publicmarker'=>$markertxt,
            'publicdir'=>$rel_dir_path,
            'token'=>$dl_url,
            'pictures'=>$pictures_json_txt,
            'extrasymbols'=>$extraSymbolList,
            'gpxedit_version'=>'',
            'gpxmotion_version'=>'',
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $response->setHeaders(Array('X-Frame-Options'=>''));
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
                $dl_url = $token.'?path='.encodeURIComponent($path);
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
                    $sqlgeomar = '
                        SELECT id, trackpath, marker
                        FROM *PREFIX*gpxpod_tracks
                        WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($user).'
                              AND trackpath LIKE '.$this->db_quote_escape_string($rel_dir_path.'%').' ;';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    $markertxt = '{"markers" : {';
                    while ($row = $req->fetch()) {
                        if (dirname($row['trackpath']) === $rel_dir_path) {
                            $trackname = basename($row['trackpath']);
                            $markertxt .= '"'.$row['id'].'": '.$row['marker'];
                            $markertxt .= ',';
                        }
                    }
                    $req->closeCursor();

                    $markertxt = rtrim($markertxt, ',');
                    $markertxt .= '}}';
                }
                else{
                    return "This directory is not a public share";
                }
            }
            else{
                return "This directory is not a public share";
            }
            $pictures_json_txt = $this->getGeoPicsFromFolder($rel_dir_path, false, $user);
        }

        $tss = $this->getUserTileServers('tile', $user, $_GET['layer']);
        $tssw = $this->getUserTileServers('tilewms', $user, $_GET['layer']);
        $oss = $this->getUserTileServers('overlay', $user, $_GET['overlay']);
        $ossw = $this->getUserTileServers('overlaywms', $user, $_GET['overlay']);

        $extraSymbolList = $this->getExtraSymbolList();

        // PARAMS to send to template

        require_once('tileservers.php');
        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>$user,
            'hassrtm'=>false,
            'basetileservers'=>$baseTileServers,
            'usertileservers'=>$tss,
            'useroverlayservers'=>$oss,
            'usertileserverswms'=>$tssw,
            'useroverlayserverswms'=>$ossw,
            'publicgpx'=>'',
            'publicmarker'=>$markertxt,
            'publicdir'=>$rel_dir_path,
            'token'=>$dl_url,
            'pictures'=>$pictures_json_txt,
            'extrasymbols'=>$extraSymbolList,
            'gpxedit_version'=>'',
            'gpxmotion_version'=>'',
            'gpxpod_version'=>$this->appVersion
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $response->setHeaders(Array('X-Frame-Options'=>''));
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
    public function deleteTracks($paths) {
        $uf = \OC::$server->getUserFolder($this->userId);
        $done = False;
        $deleted = '';
        $notdeleted = '';
        $message = '';

        foreach ($paths as $path) {
            $cleanPath = str_replace(array('../', '..\\'), '', $path);
            if ($uf->nodeExists($cleanPath)){
                $file = $uf->get($cleanPath);
                if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                    $file->isDeletable()
                ) {
                    $file->delete();
                    $deleted .= $cleanPath.', ';
                }
                else {
                    $notdeleted .= $cleanPath.', ';
                }
            }
        }
        $done = True;

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
