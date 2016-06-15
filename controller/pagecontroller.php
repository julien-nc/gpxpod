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
        if (substr($file, 0, 1) == '.') continue;
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
    private $userAbsoluteDataPath;
    private $absPathToGpxvcomp;
    private $absPathToGpxPod;
    private $shareManager;
    private $dbconnection;

    public function __construct($AppName, IRequest $request, $UserId, $userfolder, $config, $shareManager){
        parent::__construct($AppName, $request);
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
        $this->shareManager = $shareManager;
        // paths to python scripts
        $this->absPathToGpxvcomp = getcwd().'/apps/gpxpod/gpxvcomp.py';
        $this->absPathToGpxPod = getcwd().'/apps/gpxpod/gpxpod.py';
    }

    public function getUserTileServers(){
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
            if ($file->getType() == \OCP\Files\FileInfo::TYPE_FILE and
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
            'tileservers'=>$tss
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
    public function gpxvcomp() {
        $userFolder = \OC::$server->getUserFolder();
        $abs_path_to_gpxvcomp = $this->absPathToGpxvcomp;
        $data_folder = $this->userAbsoluteDataPath;

        $gpxs = Array();

        $tempdir = $data_folder.'/../cache/'.rand();
        mkdir($tempdir);

        // gpx in GET parameters
        if (!empty($_GET)){
            $subfolder = str_replace(array('../', '..\\'), '',  $_GET['subfolder']);
            for ($i=1; $i<=10; $i++){
                if (isset($_GET['name'.$i]) and $_GET['name'.$i] != ""){
                    $name = str_replace(array('/', '\\'), '',  $_GET['name'.$i]);

                    $file = $userFolder->get($subfolder.'/'.$name);
                    $content = $file->getContent();

                    file_put_contents($tempdir.'/'.$name, $content);
                    array_push($gpxs, $name);
                }
            }
        }

        if (count($gpxs)>0){
            // then we process the files
            $cmdparams = "";
            foreach($gpxs as $gpx){
                $shella = escapeshellarg($gpx);
                $cmdparams .= " $shella";
            }
            chdir("$tempdir");
            exec(escapeshellcmd($abs_path_to_gpxvcomp.' '.$cmdparams),
                $output, $returnvar);
        }

        // PROCESS error management

        $python_error_output = null;
        if (count($gpxs)>0 and $returnvar != 0){
            $python_error_output = $output;
        }

        // GET geojson content
        // then delete gpx and geojson

        $geojson = Array();
        $stats = Array();
        if (count($gpxs)>0){
            foreach($gpxs as $gpx1){
                $stats[$gpx1] = json_decode(file_get_contents($gpx1.'.stats'));
                unlink($gpx1.'.stats');
                foreach($gpxs as $gpx2){
                    if ($gpx1 !== $gpx2){
                        $geojson[$gpx1.$gpx2] = file_get_contents($gpx1.$gpx2.'.geojson');
                        unlink($gpx1.$gpx2.'.geojson');
                    }
                }
            }
            foreach($gpxs as $gpx){
                unlink($gpx);
            }
        }

        if (!rmdir($tempdir)){
            error_log('Problem deleting temporary dir on server');
        }

        $tss = $this->getUserTileServers();

        // PARAMS to send to template

        $params = [
            'python_error_output'=>$python_error_output,
            'python_return_var'=>$returnvar,
            'gpxs'=>$gpxs,
            'stats'=>$stats,
            'geojson'=>$geojson,
            'tileservers'=>$tss
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function gpxvcompp() {
        $abs_path_to_gpxvcomp = $this->absPathToGpxvcomp;
        $data_folder = $this->userAbsoluteDataPath;

        $gpxs = Array();

        $tempdir = $data_folder.'/../cache/'.rand();
        mkdir($tempdir);

        // Get uploaded files and copy them in temp dir

        // we uploaded a gpx by the POST form
        if (!empty($_POST)){
            // we copy each gpx in the tempdir
            for ($i=1; $i<=10; $i++){
                if (isset($_FILES["gpx$i"]) and $_FILES["gpx$i"]['name'] != ""){
                    $name = str_replace(" ","_",$_FILES["gpx$i"]['name']);
                    copy($_FILES["gpx$i"]['tmp_name'], "$tempdir/$name");
                    array_push($gpxs, $name);
                }
            }
        }

        // Process gpx files

        if (count($gpxs)>0){
            // then we process the files
            $cmdparams = "";
            foreach($gpxs as $gpx){
                $shella = escapeshellarg($gpx);
                $cmdparams .= " $shella";
            }
            chdir("$tempdir");
            exec(escapeshellcmd($abs_path_to_gpxvcomp.' '.$cmdparams),
                $output, $returnvar);
        }

        // Process error management

        $python_error_output = null;
        if (count($gpxs)>0 and $returnvar != 0){
            $python_error_output = $output;
        }

        // GET geojson content
        // then delete gpx and geojson

        $geojson = Array();
        $stats = Array();
        if (count($gpxs)>0){
            foreach($gpxs as $gpx1){
                $stats[$gpx1] = json_decode(file_get_contents($gpx1.'.stats'));
                unlink($gpx1.'.stats');
                foreach($gpxs as $gpx2){
                    if ($gpx1 !== $gpx2){
                        $geojson[$gpx1.$gpx2] = file_get_contents($gpx1.$gpx2.'.geojson');
                        unlink($gpx1.$gpx2.'.geojson');
                    }
                }
            }
            foreach($gpxs as $gpx){
                unlink($gpx);
            }
        }

        if (!rmdir($tempdir)){
            error_log('Problem deleting temporary dir on server');
        }

        $tss = $this->getUserTileServers();

        // PARAMS to send to template

        $params = [
            'python_error_output'=>$python_error_output,
            'python_return_var'=>$returnvar,
            'gpxs'=>$gpxs,
            'stats'=>$stats,
            'geojson'=>$geojson,
            'tileservers'=>$tss
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
     * Ajax geojson retrieval
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
     * Ajax colored geojson retrieval
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
     * Ajax markers json retrieval
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getmarkers($subfolder, $scantype) {

        // now considers encrypted storages and what's shared with the user
        // create a temp dir in cache, copy files to process in clear version in this dir
        // then process the cached dir,
        // then put the results in DB
        // then get all the markers from DB

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
            if ($ff->getType() == \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                endswith($ff->getName(), '.kml')
            ){
                array_push($kmlfiles, $ff);
            }
        }
        foreach ($userFolder->get($subfolder)->search(".KML") as $ff){
            if ($ff->getType() == \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                endswith($ff->getName(), '.KML')
            ){
                array_push($kmlfiles, $ff);
            }
        }
        $tcxfiles = Array();
        foreach ($userFolder->get($subfolder)->search(".tcx") as $ff){
            if ($ff->getType() == \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                endswith($ff->getName(), '.tcx')
            ){
                array_push($tcxfiles, $ff);
            }
        }
        foreach ($userFolder->get($subfolder)->search(".TCX") as $ff){
            if ($ff->getType() == \OCP\Files\FileInfo::TYPE_FILE and
                dirname($ff->getPath()) === $subfolder_path and
                endswith($ff->getName(), '.TCX')
            ){
                array_push($tcxfiles, $ff);
            }
        }

        // convert kmls
        if ($userFolder->nodeExists($subfolder) and
            $userFolder->get($subfolder)->getType() == \OCP\Files\FileInfo::TYPE_FOLDER){
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
            $userFolder->get($subfolder)->getType() == \OCP\Files\FileInfo::TYPE_FOLDER){

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
                if ($ff->getType() == \OCP\Files\FileInfo::TYPE_FILE and
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
                if ($returnvar == 0){
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
            $userFolder->get($subfolder)->getType() == \OCP\Files\FileInfo::TYPE_FOLDER){
            $python_error_output = $output;
            array_push($python_error_output, ' ');
            array_push($python_error_output, 'Return code : '.$returnvar);
            if ($returnvar != 0){
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
                    $userFolder->get($row["trackpath"])->getType() == \OCP\Files\FileInfo::TYPE_FILE){
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
    public function cleanDbFromAbsentFiles($subfolder) {
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
                    $userFolder->get($row["trackpath"])->getType() != \OCP\Files\FileInfo::TYPE_FILE){
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
     * Ajax python process kill
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function killpython($word) {
        $data_folder = $this->userAbsoluteDataPath;
        $command =
        "kill -9 `ps aux | grep python | grep ".$this->userId
        ." | grep '../cache' | awk '{print $2}'`".' 2>&1';
        exec($command, $output, $returnvar);
        $response = new DataResponse(
            [
                'resp'=>$returnvar
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
                    $tmpfolder->getPath() !== "/" and $dl_url == null){
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
                        if ($share->getPassword() == null){
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
            'publicgeo'=>$geocontent,
            'publicgpx'=>$gpxcontent,
            'publicmarker'=>$markercontent,
            'token'=>$dl_url
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
    public function cleanMarkersAndGeojsons($forall) {
        $del_all = ($forall === 'all');
        $userFolder = \OC::$server->getUserFolder();
        $userfolder_path = $userFolder->getPath();

        $types = Array(".gpx.geojson", ".gpx.geojson.colored", ".gpx.marker");
        $types_with_up = Array(".gpx.geojson", ".gpx.geojson.colored", ".gpx.marker",
                               ".GPX.geojson", ".GPX.geojson.colored", ".GPX.marker");
        $all = Array();
        foreach($types as $ext){
            $search = $userFolder->search($ext);
            $merge = array_merge($all, $search);
            $all = $merge;
        }
        $all = array_unique($all);
        $todel = Array();
        $problems = '<ul>';
        $deleted = '<ul>';
        foreach($all as $file){
            if ($file->getType() == \OCP\Files\FileInfo::TYPE_FILE){
                $name = $file->getName();
                foreach($types_with_up as $ext){
                    if (endswith($name, $ext)){
                        $rel_path = str_replace($userfolder_path, '', $file->getPath());
                        $rel_path = str_replace('//', '/', $rel_path);
                        $gpx_rel_path = str_replace($ext, '.gpx', $rel_path);
                        if ($del_all or $userFolder->nodeExists($gpx_rel_path)){
                            array_push($todel, $file);
                        }
                    }
                }
            }
        }
        foreach($todel as $ftd){
            $rel_path = str_replace($userfolder_path, '', $ftd->getPath());
            $rel_path = str_replace('//', '/', $rel_path);
            if ($ftd->isDeletable()){
                $ftd->delete();
                $deleted .= '<li>'.$rel_path."</li>\n";
            }
            else{
                $problems .= '<li>Impossible to delete '.$rel_path."</li>\n";
            }
        }
        $problems .= '</ul>';
        $deleted .= '</ul>';

        $response = new DataResponse(
            [
                'deleted'=>$deleted,
                'problems'=>$problems
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
    public function addTileServer($servername, $serverurl) {
        // first we check it does not already exist
        $sqlts = 'SELECT `servername` FROM *PREFIX*gpxpod_tile_servers ';
        $sqlts .= 'WHERE `user`="'.$this->userId.'" ';
        $sqlts .= 'AND `servername`="'.$servername.'" ';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $ts = null;
        while ($row = $req->fetch()){
            $ts = $row["servername"];
            break;
        }
        $req->closeCursor();

        // then if not, we insert it
        if ($ts === null){
            $sql = 'INSERT INTO *PREFIX*gpxpod_tile_servers';
            $sql .= ' (`user`,`servername`,`url`) ';
            $sql .= 'VALUES ("'.$this->userId.'",';
            $sql .= '"'.$servername.'",';
            $sql .= '"'.$serverurl.'");';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();
            $ok = 1;
        }
        else{
            $ok = 0;
        }

        $response = new DataResponse(
            [
                'done'=>$ok
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
    public function deleteTileServer($servername) {
        $sqldel = 'DELETE FROM *PREFIX*gpxpod_tile_servers ';
        $sqldel .= 'WHERE `user`="'.$this->userId.'" AND `servername`="';
        $sqldel .= $servername.'";';
        //$sqldel .= 'WHERE `user`="'.$this->userId.'";';
        $req = $this->dbconnection->prepare($sqldel);
        $req->execute();
        $req->closeCursor();

        $response = new DataResponse(
            [
                'done'=>1
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
    public function processTrackElevations($trackname, $folder, $smooth) {
        // we create a tmpdir
        // we copy gpxfile
        // we srtmify
        // we update DB
        // we say it's ok
        $userFolder = \OC::$server->getUserFolder();
        $data_folder = $this->userAbsoluteDataPath;
        $gpxelePath = getProgramPath('gpxelevations');
        $path_to_gpxpod = $this->absPathToGpxPod;
        $success = False;

        $filerelpath = $folder.'/'.$trackname;

        if ($userFolder->nodeExists($filerelpath) and
            $userFolder->get($filerelpath)->getType() == \OCP\Files\FileInfo::TYPE_FILE and
            $gpxelePath !== null
        ){
            $tempdir = $data_folder.'/../cache/'.rand();
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
                escapeshellcmd(
                    $gpxelePath.' '.$cmdparams
                ),
                $output, $returnvar
            );

            // overwrite original gpx files with corrected ones
            if ($returnvar == 0){
                if (endswith($gpx_clear_path, '.GPX')){
                    rename(
                        str_replace('.GPX', '_with_elevations.gpx', $gpx_clear_path),
                        $gpx_clear_path
                    );
                }
                else{
                    rename(
                        str_replace('.gpx', '_with_elevations.gpx', $gpx_clear_path),
                        $gpx_clear_path
                    );
                }
            }
            // delete cache
            foreach(globRecursive($tempdir.'/.cache/srtm', '*', False) as $cachefile){
                unlink($cachefile);
            }
            rmdir($tempdir.'/.cache/srtm');
            rmdir($tempdir.'/.cache');

            // we process with gpxpod.py
            exec(escapeshellcmd(
                $path_to_gpxpod.' '.escapeshellarg($tempdir.'/')
                .' '.escapeshellarg('newonly')
            ).' 2>&1',
            $output, $returnvar);

            $result_gpx_path = $gpx_clear_path;
            $geo_path = $result_gpx_path.'.geojson';
            $geoc_path = $result_gpx_path.'.geojson.colored';
            $mar_path = $result_gpx_path.'.marker';
            if (file_exists($geo_path) and file_exists($geoc_path) and file_exists($mar_path)){
                $gpx_relative_path = $folder.'/'.basename($result_gpx_path);
                $geo_content = str_replace("'", '"', file_get_contents($geo_path));
                $geoc_content = str_replace("'", '"', file_get_contents($geoc_path));
                $mar_content = str_replace("'", '"', file_get_contents($mar_path));

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
                    $success = True;
                }
                catch (Exception $e) {
                    error_log("Exception in Owncloud : ".$e->getMessage());
                }
            }

            // delete tmpdir
            foreach(globRecursive($tempdir, '*') as $fpath){
                unlink($fpath);
            }
            rmdir($tempdir);
        }

        $response = new DataResponse(
            [
                'done'=>$success
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
