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

use OCP\IURLGenerator;
use OCP\IConfig;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

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
    private $shareManager;
    private $dbconnection;

    public function __construct($AppName, IRequest $request, $UserId,
                                $userfolder, $config, $shareManager){
        parent::__construct($AppName, $request);
        $this->appVersion = $config->getAppValue('gpxpod', 'installed_version');
        $this->userId = $UserId;
        if ($UserId !== '' and $userfolder !== null){
            // path of user files folder relative to DATA folder
            $this->userfolder = $userfolder;
            // IConfig object
            $this->config = $config;
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
        $this->absPathToGpxPod = getcwd().'/apps/gpxpod/gpxpod.py';
    }

    private function getUserTileServers(){
        // custom tile servers management
        $sqlts = 'SELECT `servername`,`url` FROM *PREFIX*gpxpod_tile_servers ';
        $sqlts .= 'WHERE `user`="'.$this->userId.'";';
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

        // PARAMS to view

        sort($alldirs);
        $params = [
            'dirs'=>$alldirs,
            'gpxcomp_root_url'=>$gpxcomp_root_url,
            'username'=>$this->userId,
            'extra_scan_type'=>$extraScanType,
            'tileservers'=>$tss,
            'publicgeo'=>'',
            'publicgpx'=>'',
            'publicmarker'=>'',
            'token'=>'',
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
        $path = $folder.'/'.$title;

        $sqlgeo = 'SELECT `geojson` FROM *PREFIX*gpxpod_tracks ';
        $sqlgeo .= 'WHERE `user`="'.$this->userId.'" ';
        $sqlgeo .= 'AND `trackpath`="'.$path.'" ';
        $req = $this->dbconnection->prepare($sqlgeo);
        $req->execute();
        $geo = null;
        while ($row = $req->fetch()){
            $geo = $row["geojson"];
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
        $path = $folder.'/'.$title;

        $sqlgeoc = 'SELECT `geojson_colored` FROM *PREFIX*gpxpod_tracks ';
        $sqlgeoc .= 'WHERE `user`="'.$this->userId.'" ';
        $sqlgeoc .= 'AND `trackpath`="'.$path.'" ';
        $req = $this->dbconnection->prepare($sqlgeoc);
        $req->execute();
        $geoc = null;
        while ($row = $req->fetch()){
            $geoc = $row["geojson_colored"];
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
            $sqlgpx = 'SELECT `trackpath` FROM *PREFIX*gpxpod_tracks ';
            $sqlgpx .= 'WHERE `user`="'.$this->userId.'"; ';
            $req = $this->dbconnection->prepare($sqlgpx);
            $req->execute();
            $gpxs_in_db = Array();
            while ($row = $req->fetch()){
                array_push($gpxs_in_db, $row["trackpath"]);
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
            exec(escapeshellcmd(
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
                            $sql .= ' (`user`,`trackpath`,`marker`,`geojson`,`geojson_colored`) ';
                            $sql .= 'VALUES ("'.$this->userId.'",';
                            $sql .= '"'.$gpx_relative_path.'",';
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
                            $sqlupd .= 'SET `marker`=\''.$mar_content.'\', ';
                            $sqlupd .= '`geojson`=\''.$geo_content.'\', ';
                            $sqlupd .= '`geojson_colored`=\''.$geoc_content.'\' ';
                            $sqlupd .= 'WHERE `user`="'.$this->userId.'" AND ';
                            $sqlupd .= '`trackpath`="'.$gpx_relative_path.'"; ';
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
            foreach(globRecursive($tempdir, '*') as $fpath){
                unlink($fpath);
            }
            rmdir($tempdir);
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
        $markertxt = "{\"markers\" : [";
        // DB style
        $sqlmar = 'SELECT `trackpath`, `marker` FROM *PREFIX*gpxpod_tracks ';
        $sqlmar .= 'WHERE `user`="'.$this->userId.'" ';
        $sqlmar .= 'AND `trackpath` LIKE \''.$subfolder.'%\'; ';
        $req = $this->dbconnection->prepare($sqlmar);
        $req->execute();
        while ($row = $req->fetch()){
            if (dirname($row["trackpath"]) === $subfolder){
                // if the gpx file exists, ok, if not : delete DB entry
                if ($userFolder->nodeExists($row["trackpath"]) and
                    $userFolder->get($row["trackpath"])->getType() === \OCP\Files\FileInfo::TYPE_FILE){
                    $markertxt .= $row["marker"];
                    $markertxt .= ",";
                }
            }
        }
        $req->closeCursor();

        // CLEANUP DB for non-existing files
        $this->cleanDbFromAbsentFiles($subfolder);

        $markertxt = rtrim($markertxt, ",");
        $markertxt .= "]}";

        $response = new DataResponse(
            [
                'markers'=>$markertxt,
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
     * delete from DB all entries refering to absent files
     * optionnal parameter : folder to clean
     */
    private function cleanDbFromAbsentFiles($subfolder) {
        $userFolder = \OC::$server->getUserFolder();
        $gpx_paths_to_del = Array();

        $sqlmar = 'SELECT `trackpath` FROM *PREFIX*gpxpod_tracks ';
        $sqlmar .= 'WHERE `user`="'.$this->userId.'"; ';
        $req = $this->dbconnection->prepare($sqlmar);
        $req->execute();
        while ($row = $req->fetch()){
            if (dirname($row["trackpath"]) === $subfolder or $subfolder === null){
                // delete DB entry if the file does not exist
                if (
                    (! $userFolder->nodeExists($row["trackpath"])) or
                    $userFolder->get($row["trackpath"])->getType() !== \OCP\Files\FileInfo::TYPE_FILE){
                    array_push($gpx_paths_to_del, $row["trackpath"]);
                }
            }
        }

        if (count($gpx_paths_to_del) > 0){
            $sqldel = 'DELETE FROM *PREFIX*gpxpod_tracks ';
            $sqldel .= 'WHERE `user`="'.$this->userId.'" AND (`trackpath`="';
            $sqldel .= implode('" OR `trackpath`="', $gpx_paths_to_del);
            $sqldel .= '");';
            $req = $this->dbconnection->prepare($sqldel);
            $req->execute();
            $req->closeCursor();
        }
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

                // CHECK if file is inside a shared folder
                $tmpfolder = $thefile->getParent();
                while ($tmpfolder->getPath() !== $uf->getPath() and
                    $tmpfolder->getPath() !== "/" and $dl_url === null){
                    //error_log("TMP NO : ".$tmpfolder->getPath());
                    $shares_folder = $this->shareManager->getSharesBy($_GET['user'],
                        \OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
                    if (count($shares_folder) > 0){
                        foreach($shares_folder as $share){
                            if ($share->getPassword() === null){
                                // one folder above the file is shared without passwd
                                $token = $share->getToken();
                                $subpath = str_replace($tmpfolder->getPath(), '', $thefile->getPath());
                                //error_log('YES token : '.$token.' and subpath : '.$subpath);
                                $dl_url = $token.'/download?path='.rtrim(dirname($subpath), '/');
                                $dl_url .= '&files='.basename($subpath);
                                //error_log('SO url : '.$url);

                                break;
                            }
                        }
                    }
                    $tmpfolder = $tmpfolder->getParent();
                }
                // CHECK if file is shared
                $shares = $this->shareManager->getSharesBy($_GET['user'],
                    \OCP\Share::SHARE_TYPE_LINK, $thefile, false, 1, 0);
                if (count($shares) > 0){
                    foreach($shares as $share){
                        if ($share->getPassword() === null){
                            $dl_url = $share->getToken();
                            break;
                        }
                    }
                }

                if ($dl_url !== null){
                    // gpx exists and is shared with no password
                    $gpxcontent = $thefile->getContent();

                    $sqlgeomar = 'SELECT `geojson`,`marker` FROM *PREFIX*gpxpod_tracks ';
                    $sqlgeomar .= 'WHERE `user`="'.$user.'" ';
                    $sqlgeomar .= 'AND `trackpath`="'.$path.'" ';
                    $req = $dbconnection->prepare($sqlgeomar);
                    $req->execute();
                    $geo = null;
                    while ($row = $req->fetch()){
                        $geocontent = $row["geojson"];
                        $markercontent = $row["marker"];
                        break;
                    }
                    $req->closeCursor();

                }
                else{
                    return "This file is not a public share";
                }
            }
            else{
                return "This file is not a public share";
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
            'publicgpx'=>$gpxcontent,
            'publicmarker'=>$markercontent,
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

}
