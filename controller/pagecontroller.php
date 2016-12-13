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

use \OC_App;

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

/*
 * search into all directories in PATH environment variable
 * to find a program and return it if found
 */
function getProgramPath($progname){
    $path_ar = explode(':',getenv('path'));
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
    private $absPathToGpxPod;
    private $absPathToPictures;
    private $shareManager;
    private $dbconnection;
    private $dbtype;
    private $dbdblquotes;
    private $appPath;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager){
        parent::__construct($AppName, $request);
        $this->appVersion = $config->getAppValue('gpxpod', 'installed_version');
        $this->appPath = \OC_App::getAppPath('gpxpod');
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
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
            // absolute path to user files folder
            $this->userAbsoluteDataPath =
                $this->config->getSystemValue('datadirectory').
                rtrim($this->userfolder->getFullPath(''), '/');

            // make cache if it does not exist
            $cachedirpath = $this->userAbsoluteDataPath.'/../cache';
            if (! is_dir($cachedirpath)){
                mkdir($cachedirpath);
            }

            $this->dbconnection = \OC::$server->getDatabaseConnection();
        }
        //$this->shareManager = \OC::$server->getShareManager();
        $this->shareManager = $shareManager;
        // paths to python scripts
        $this->absPathToGpxPod = $this->appPath.'/gpxpod.py';
        $this->absPathToPictures = $this->appPath.'/pictures.py';
    }

    private function getUserTileServers(){
        // custom tile servers management
        $sqlts = 'SELECT servername, url FROM *PREFIX*gpxpod_tile_servers ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\';';
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
        $gpxs = $userFolder->search(".gpx");
        $kmls = $userFolder->search(".kml");
        $tcxs = $userFolder->search(".tcx");
        $all = array_merge($gpxs, $kmls, $tcxs);
        $alldirs = Array();
        foreach($all as $file){
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                (
                    endswith($file->getName(), '.kml') or
                    endswith($file->getName(), '.gpx') or
                    endswith($file->getName(), '.tcx') or
                    endswith($file->getName(), '.KML') or
                    endswith($file->getName(), '.GPX') or
                    endswith($file->getName(), '.TCX')
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

        // extra symbols
        $gpxEditDataDirPath = $this->config->getSystemValue('datadirectory').'/gpxedit';
        $extraSymbolList = Array();
        if (is_dir($gpxEditDataDirPath.'/symbols')){
            foreach(globRecursive($gpxEditDataDirPath.'/symbols', '*.png', False) as $symbolfile){
                $filename = basename($symbolfile);
                array_push($extraSymbolList, Array('smallname'=>str_replace('.png', '', $filename), 'name'=>$filename));
            }
        }

        // PARAMS to view

        sort($alldirs);
        $params = [
            'dirs'=>$alldirs,
            'gpxcomp_root_url'=>$gpxcomp_root_url,
            'username'=>$this->userId,
            'extra_scan_type'=>$extraScanType,
            'tileservers'=>$tss,
            'publicgeo'=>'',
            'publicgeocol'=>'',
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
     * Ajax geojson retrieval from DB
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getgeo($title, $folder) {
        $cleanFolder = $folder;
        if ($folder === '/'){
            $cleanFolder = '';
        }
        $path = $cleanFolder.'/'.$title;

        $sqlgeo = 'SELECT geojson FROM *PREFIX*gpxpod_tracks ';
        $sqlgeo .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlgeo .= 'AND trackpath=\''.$path.'\' ';
        $req = $this->dbconnection->prepare($sqlgeo);
        $req->execute();
        $geo = null;
        while ($row = $req->fetch()){
            $geo = $row['geojson'];
            break;
        }
        $req->closeCursor();

        $response = new DataResponse(
            [
                'track'=>$geo
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
     * Ajax colored geojson retrieval from DB
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getgeocol($title, $folder) {
        $cleanFolder = $folder;
        if ($folder === '/'){
            $cleanFolder = '';
        }
        $path = $cleanFolder.'/'.$title;

        $sqlgeoc = 'SELECT geojson_colored FROM *PREFIX*gpxpod_tracks ';
        $sqlgeoc .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlgeoc .= 'AND trackpath=\''.$path.'\' ';
        $req = $this->dbconnection->prepare($sqlgeoc);
        $req->execute();
        $geoc = null;
        while ($row = $req->fetch()){
            $geoc = $row['geojson_colored'];
            break;
        }
        $req->closeCursor();

        $response = new DataResponse(
            [
                'track'=>$geoc
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
     * Ajax markers json retrieval from DB
     *
     * First convert kml or tcx files if necessary.
     * Then copy files to a temporary directory (decrypt them if necessary).
     * Then correct elevations if it was asked.
     * Then process the files to produce .geojson* and .marker files.
     * Then INSERT or UPDATE the database with processed data.
     * Then get the markers for all gpx files in the target folder
     * Then clean useless database entries (for files that no longer exist)
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getmarkers($subfolder, $scantype) {
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();
        $subfolder_path = $userFolder->get($subfolder)->getPath();

        $data_folder = $this->userAbsoluteDataPath;
        $subfolder = str_replace(array('../', '..\\'), '',  $subfolder);

        // make temporary dir to process decrypted files
        $tempdir = $data_folder.'/../cache/'.rand();
        mkdir($tempdir);

        $path_to_gpxpod = $this->absPathToGpxPod;

        // Convert KML to GPX
        // only if we want to display a folder AND it exists AND we want
        // to compute AND we find GPSBABEL AND file was not already converted

        if ($subfolder === '/'){
            $subfolder = '';
        }
        $path_to_process = $data_folder.$subfolder;

        // find kmls
        $kmlfiles = Array();
        foreach ($userFolder->get($subfolder)->search(".kml") as $ff){
            if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                endswith($ff->getName(), '.kml')
            ){
                array_push($kmlfiles, $ff);
            }
        }
        foreach ($userFolder->get($subfolder)->search(".KML") as $ff){
            if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                endswith($ff->getName(), '.KML')
            ){
                array_push($kmlfiles, $ff);
            }
        }
        $tcxfiles = Array();
        foreach ($userFolder->get($subfolder)->search(".tcx") as $ff){
            if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                endswith($ff->getName(), '.tcx')
            ){
                array_push($tcxfiles, $ff);
            }
        }
        foreach ($userFolder->get($subfolder)->search(".TCX") as $ff){
            if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                endswith($ff->getName(), '.TCX')
            ){
                array_push($tcxfiles, $ff);
            }
        }

        // convert kmls
        if ($userFolder->nodeExists($subfolder) and
            $userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER){
            $gpsbabel_path = getProgramPath('gpsbabel');

            if ($gpsbabel_path !== null){
                foreach($kmlfiles as $kmlf){
                    $kmlname = $kmlf->getName();
                    $gpx_targetname = str_replace('.kml', '.gpx', $kmlname);
                    $gpx_targetname = str_replace('.KML', '.gpx', $gpx_targetname);
                    if (! $userFolder->nodeExists($subfolder.'/'.$gpx_targetname)){
                        // we read content, then write it in the tempdir
                        // then convert, then read content then write it back in
                        // the real dir

                        $kmlcontent = $kmlf->getContent();
                        $kml_clear_path = $tempdir.'/'.$kmlname;
                        $gpx_target_clear_path = $tempdir.'/'.$gpx_targetname;
                        file_put_contents($kml_clear_path, $kmlcontent);

                        $args = Array('-i', 'kml', '-f', $kml_clear_path, '-o',
                            'gpx', '-F', $gpx_target_clear_path);
                        $cmdparams = '';
                        foreach($args as $arg){
                            $shella = escapeshellarg($arg);
                            $cmdparams .= " $shella";
                        }
                        exec(
                            escapeshellcmd(
                                $gpsbabel_path.' '.$cmdparams
                            ),
                            $output, $returnvar
                        );
                        $gpx_clear_content = file_get_contents($gpx_target_clear_path);
                        $gpx_file = $userFolder->newFile($subfolder.'/'.$gpx_targetname);
                        $gpx_file->putContent($gpx_clear_content);
                    }
                }
                foreach($tcxfiles as $tcxf){
                    $tcxname = $tcxf->getName();
                    $gpx_targetname = str_replace('.tcx', '.gpx', $tcxname);
                    $gpx_targetname = str_replace('.TCX', '.gpx', $gpx_targetname);

                    if (! $userFolder->nodeExists($subfolder.'/'.$gpx_targetname)){
                        // we read content, then write it in the tempdir
                        // then convert, then read content then write it back in
                        // the real dir
                        $tcxcontent = $tcxf->getContent();
                        $tcx_clear_path = $tempdir.'/'.$tcxname;
                        $gpx_target_clear_path = $tempdir.'/'.$gpx_targetname;
                        file_put_contents($tcx_clear_path, $tcxcontent);

                        $args = Array('-i', 'gtrnctr', '-f', $tcx_clear_path, '-o',
                            'gpx', '-F', $gpx_target_clear_path);
                        $cmdparams = '';
                        foreach($args as $arg){
                            $shella = escapeshellarg($arg);
                            $cmdparams .= " $shella";
                        }
                        exec(
                            escapeshellcmd(
                                $gpsbabel_path.' '.$cmdparams
                            ),
                            $output, $returnvar
                        );

                        $gpx_clear_content = file_get_contents($gpx_target_clear_path);
                        $gpx_file = $userFolder->newFile($subfolder.'/'.$gpx_targetname);
                        $gpx_file->putContent($gpx_clear_content);
                    }
                }
            }
        }

        // PROCESS gpx files and fill DB

        //// DELETION
        //$sqldel = 'DELETE FROM *PREFIX*gpxpod_tracks ';
        //$sqldel .= 'WHERE 1; ';
        //$req = $this->dbconnection->prepare($sqldel);
        //$req->execute();
        //$req->closeCursor();

        $path_to_process = $data_folder.$subfolder;
        if ($userFolder->nodeExists($subfolder) and
            $userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER){

            // find gpxs db style
            $sqlgpx = 'SELECT trackpath FROM *PREFIX*gpxpod_tracks ';
            $sqlgpx .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\'; ';
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
            if ($gpxelePath !== null and
                ($scantype === 'srtm' or
                 $scantype === 'srtms' or
                 $scantype === 'newsrtm' or
                 $scantype === 'newsrtms') and
                count($gpxs_to_process) > 0){
                $tmpgpxsmin = globRecursive($tempdir, '*.gpx', False);
                $tmpgpxsmaj = globRecursive($tempdir, '*.GPX', False);
                $tmpgpxs = array_merge($tmpgpxsmin, $tmpgpxsmaj);
                $args = Array();
                foreach($tmpgpxs as $tmpgpx){
                    array_push($args, $tmpgpx);
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
                    escapeshellcmd(
                        $gpxelePath.' '.$cmdparams
                    ),
                    $output, $returnvar
                );

                // overwrite original gpx files with corrected ones
                if ($returnvar === 0){
                    foreach($tmpgpxs as $tmpgpx){
                        if (endswith($tmpgpx, '.GPX')){
                            rename(
                                str_replace('.GPX', '_with_elevations.gpx', $tmpgpx),
                                $tmpgpx
                            );
                        }
                        else{
                            rename(
                                str_replace('.gpx', '_with_elevations.gpx', $tmpgpx),
                                $tmpgpx
                            );
                        }
                    }
                }
                // delete cache
                foreach(globRecursive($tempdir.'/.cache/srtm', '*', False) as $cachefile){
                    unlink($cachefile);
                }
                rmdir($tempdir.'/.cache/srtm');
                rmdir($tempdir.'/.cache');
            }

            // we execute gpxpod.py
            exec(escapeshellcmd('python '.
                $path_to_gpxpod.' '.escapeshellarg($clear_path_to_process)
                .' '.escapeshellarg($processtype_arg)
            ).' 2>&1',
            $output, $returnvar);

            // DB STYLE
            $resgpxsmin = globRecursive($tempdir, '*.gpx', False);
            $resgpxsmaj = globRecursive($tempdir, '*.GPX', False);
            $resgpxs = array_merge($resgpxsmin, $resgpxsmaj);
            foreach($resgpxs as $result_gpx_path){
                $geo_path = $result_gpx_path.'.geojson';
                $geoc_path = $result_gpx_path.'.geojson.colored';
                $mar_path = $result_gpx_path.'.marker';
                if (file_exists($geo_path) and file_exists($geoc_path) and file_exists($mar_path)){
                    $gpx_relative_path = $subfolder.'/'.basename($result_gpx_path);
                    $geo_content = str_replace("'", '"', file_get_contents($geo_path));
                    $geoc_content = str_replace("'", '"', file_get_contents($geoc_path));
                    $mar_content = str_replace("'", '"', file_get_contents($mar_path));

                    if (! in_array($gpx_relative_path, $gpxs_in_db)){
                        try{
                            $sql = 'INSERT INTO *PREFIX*gpxpod_tracks';
                            $sql .= ' ('.$this->dbdblquotes.'user'.$this->dbdblquotes.',trackpath,marker,geojson,geojson_colored) ';
                            $sql .= 'VALUES (\''.$this->userId.'\',';
                            $sql .= '\''.$gpx_relative_path.'\',';
                            $sql .= '\''.$mar_content.'\',';
                            $sql .= '\''.$geo_content.'\',';
                            $sql .= '\''.$geoc_content.'\');';
                            $req = $this->dbconnection->prepare($sql);
                            $req->execute();
                            $req->closeCursor();
                        }
                        catch (Exception $e) {
                            error_log("Exception in Owncloud : ".$e->getMessage());
                        }
                    }
                    else{
                        try{
                            $sqlupd = 'UPDATE *PREFIX*gpxpod_tracks ';
                            $sqlupd .= 'SET marker=\''.$mar_content.'\', ';
                            $sqlupd .= 'geojson=\''.$geo_content.'\', ';
                            $sqlupd .= 'geojson_colored=\''.$geoc_content.'\' ';
                            $sqlupd .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' AND ';
                            $sqlupd .= 'trackpath=\''.$gpx_relative_path.'\'; ';
                            $req = $this->dbconnection->prepare($sqlupd);
                            $req->execute();
                            $req->closeCursor();
                        }
                        catch (Exception $e) {
                            error_log("Exception in Owncloud : ".$e->getMessage());
                        }
                    }
                }
            }
            // delete tmpdir
            delTree($tempdir);
        }
        else{
            //die($path_to_process.' does not exist');
        }

        // PROCESS error management

        $python_error_output = null;
        $python_error_output_cleaned = array();
        if ($userFolder->nodeExists($subfolder) and
            $userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER){
            $python_error_output = $output;
            array_push($python_error_output, ' ');
            array_push($python_error_output, 'Return code : '.$returnvar);
            if ($returnvar !== 0){
                foreach($python_error_output as $errline){
                    error_log($errline);
                }
            }
            foreach($python_error_output as $errline){
                array_push($python_error_output_cleaned, str_replace(
                    $path_to_process, 'selected_folder', $errline
                ));
            }
        }

        // info for JS

        // build markers
        //$path_to_process_relative = str_replace($data_folder, '', $path_to_process);
        $subfolder_sql = $subfolder;
        if ($subfolder === ''){
            $subfolder_sql = '/';
        }
        $markertxt = '{"markers" : [';
        // DB style
        $sqlmar = 'SELECT trackpath, marker FROM *PREFIX*gpxpod_tracks ';
        $sqlmar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        // TODO maybe remove the LIKE and just use the php filtering that is following
        // and enough
        $sqlmar .= 'AND trackpath LIKE \''.$subfolder_sql.'%\'; ';
        $req = $this->dbconnection->prepare($sqlmar);
        $req->execute();
        while ($row = $req->fetch()){
            if (dirname($row['trackpath']) === $subfolder_sql){
                // if the gpx file exists, ok, if not : delete DB entry
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
                'python_output'=>implode('<br/>',$python_error_output_cleaned)
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
     * then get the pic list and coords with pictures.py
     */
    private function getGeoPicsFromFolder($subfolder, $user=""){
        $path_to_pictures = $this->absPathToPictures;

        // if user is not given, the request comes from connected user threw getmarkers
        if ($user === ""){
            $userFolder = \OC::$server->getUserFolder();
            $data_folder = $this->userAbsoluteDataPath;
        }
        // else, it comes from a public dir
        else{
            $userFolder = \OC::$server->getUserFolder($user);
            $data_folder = $this->config->getSystemValue('datadirectory').
                rtrim($userFolder->getFullPath(''), '/');
        }
        $subfolder = str_replace(array('../', '..\\'), '',  $subfolder);
        $subfolder_path = $userFolder->get($subfolder)->getPath();

        // make temporary dir to process decrypted files
        $tempdir = $data_folder.'/../cache/'.rand();
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
        }

        // we execute pictures.py
        $clear_path_to_process = $tempdir.'/';
        exec('export PYTHON_EGG_CACHE="'.$tempdir.'"; '.
            escapeshellcmd('python '.
            $path_to_pictures.' '.escapeshellarg($clear_path_to_process)
        ).' 2>&1',
        $output2, $returnvar2);

        $pictures_json_txt = '{}';
        if (file_exists($tempdir.'/pictures.txt')){
            $pictures_json_txt = file_get_contents($tempdir.'/pictures.txt');
            $pictures_json_txt = rtrim($pictures_json_txt, "\n");
            $pictures_json_txt = rtrim($pictures_json_txt, ",");
            $pictures_json_txt = '{'.$pictures_json_txt.'}';
        }

        delTree($tempdir);

        return $pictures_json_txt;

    }

    /**
     * delete from DB all entries refering to absent files
     * optionnal parameter : folder to clean
     */
    private function cleanDbFromAbsentFiles($subfolder) {
        $userFolder = \OC::$server->getUserFolder();
        $gpx_paths_to_del = Array();

        $sqlmar = 'SELECT trackpath FROM *PREFIX*gpxpod_tracks ';
        $sqlmar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\'; ';
        $req = $this->dbconnection->prepare($sqlmar);
        $req->execute();
        while ($row = $req->fetch()){
            if (dirname($row['trackpath']) === $subfolder or $subfolder === null){
                // delete DB entry if the file does not exist
                if (
                    (! $userFolder->nodeExists($row['trackpath'])) or
                    $userFolder->get($row['trackpath'])->getType() !== \OCP\Files\FileInfo::TYPE_FILE){
                    array_push($gpx_paths_to_del, $row['trackpath']);
                }
            }
        }

        if (count($gpx_paths_to_del) > 0){
            $sqldel = 'DELETE FROM *PREFIX*gpxpod_tracks ';
            $sqldel .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' AND (trackpath=\'';
            $sqldel .= implode('\' OR trackpath=\'', $gpx_paths_to_del);
            $sqldel .= '\');';
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
    public function getPublinkDownloadURL($file, $username){
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
     * Handle public link view request
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
                    $sqlgeomar = 'SELECT geojson, geojson_colored, marker FROM *PREFIX*gpxpod_tracks ';
                    $sqlgeomar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$user.'\' ';
                    $sqlgeomar .= 'AND trackpath=\''.$path.'\' ';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    while ($row = $req->fetch()){
                        $geocontent = $row['geojson'];
                        $geocolcontent = $row['geojson_colored'];
                        $markercontent = $row['marker'];
                        break;
                    }
                    $req->closeCursor();

                }
                else{
                    return 'This file is not a public share';
                }
            }
            else{
                return 'This file is not a public share';
            }
        }

        // PARAMS to send to template

        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>'',
            'extra_scan_type'=>'',
            'tileservers'=>'',
            'publicgeo'=>$geocontent,
            'publicgeocol'=>$geocolcontent,
            'publicmarker'=>$markercontent,
            'publicdir'=>'',
            'pictures'=>'',
            'token'=>$dl_url,
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

    public function getPubfolderDownloadURL($dir, $username){
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
                            array_push($gpx_inside_thedir, $rel_file_path);
                        }
                    }

                    // get the tracks data from DB
                    $sqlgeomar = 'SELECT trackpath, geojson, ';
                    $sqlgeomar .= 'geojson_colored, marker FROM *PREFIX*gpxpod_tracks ';
                    $sqlgeomar .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$user.'\' AND (';
                    $sqlgeomar .= 'trackpath=\'';
                    $sqlgeomar .= implode('\' OR trackpath=\'', $gpx_inside_thedir);
                    $sqlgeomar .= '\');';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    $geocontent = '{';
                    $geocolcontent = '{';
                    $markertxt = '{"markers" : [';
                    while ($row = $req->fetch()){
                        $trackname = basename($row['trackpath']);
                        $geocontent .= '"'.$trackname.'":'.$row['geojson'].',';
                        $geocolcontent .= '"'.$trackname.'":'.$row['geojson_colored'].',';
                        $markertxt .= $row['marker'];
                        $markertxt .= ',';
                    }
                    $req->closeCursor();

                    $markertxt = rtrim($markertxt, ',');
                    $markertxt .= ']}';
                    $geocontent = rtrim($geocontent, ',');
                    $geocontent .= '}';
                    $geocolcontent = rtrim($geocolcontent, ',');
                    $geocolcontent .= '}';

                }
                else{
                    return "This directory is not a public share";
                }
            }
            else{
                return "This file is not a public share";
            }
            $pictures_json_txt = $this->getGeoPicsFromFolder($path, $user);
        }

        // PARAMS to send to template

        $rel_dir_path = str_replace($userfolder_path, '', $thedir->getPath());

        $params = [
            'dirs'=>Array(),
            'gpxcomp_root_url'=>'',
            'username'=>'',
            'extra_scan_type'=>'',
            'tileservers'=>'',
            'publicgeo'=>$geocontent,
            'publicgeocol'=>$geocolcontent,
            'publicmarker'=>$markertxt,
            'publicdir'=>$rel_dir_path,
            'token'=>$dl_url,
            'pictures'=>$pictures_json_txt,
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
     * @NoCSRFRequired
     */
    public function isFileShareable($trackpath, $username) {
        $uf = \OC::$server->getUserFolder($username);
        $isIt = false;

        if ($uf->nodeExists($trackpath)){
            $thefile = $uf->get($trackpath);
            if ($this->getPublinkDownloadURL($thefile, $username) !== null){
                $isIt = true;
            }
        }

        $response = new DataResponse(
            [
                'response'=>$isIt
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
     * @NoCSRFRequired
     */
    public function isFolderShareable($folderpath, $username) {
        $uf = \OC::$server->getUserFolder($username);
        $isIt = false;

        if ($uf->nodeExists($folderpath)){
            $thefolder = $uf->get($folderpath);
            if ($this->getPubfolderDownloadURL($thefolder, $username) !== null){
                $isIt = true;
            }
        }

        $response = new DataResponse(
            [
                'response'=>$isIt
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
