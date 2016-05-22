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
        }
        $this->shareManager = $shareManager;
        // paths to python scripts
        $this->absPathToGpxvcomp = getcwd().'/apps/gpxpod/gpxvcomp.py';
        $this->absPathToGpxPod = getcwd().'/apps/gpxpod/gpxpod.py';
    }

    /**
     *CAUTION: the @Stuff turns off security checks; for this page no admin is
     *         required and no CSRF check. If you don't know what CSRF is, read
     *         it up in the docs or you might create a security hole. This is
     *         basically the only required method to add this exemption, don't
     *         add it to any other method if you don't exactly know what it does
     *
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

        // PARAMS to view

        sort($alldirs);
        $params = [
            'dirs'=>$alldirs,
            'gpxcomp_root_url'=>$gpxcomp_root_url,
            'username'=>$this->userId
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
     *CAUTION: the @Stuff turns off security checks; for this page no admin is
     *         required and no CSRF check. If you don't know what CSRF is, read
     *         it up in the docs or you might create a security hole. This is
     *         basically the only required method to add this exemption, don't
     *         add it to any other method if you don't exactly know what it does
     *
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
        if (count($gpxs)>0){
            foreach($gpxs as $gpx1){
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

        // PARAMS to send to template

        $params = [
            'python_error_output'=>$python_error_output,
            'python_return_var'=>$returnvar,
            'gpxs'=>$gpxs,
            'geojson'=>$geojson
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
     *CAUTION: the @Stuff turns off security checks; for this page no admin is
     *         required and no CSRF check. If you don't know what CSRF is, read
     *         it up in the docs or you might create a security hole. This is
     *         basically the only required method to add this exemption, don't
     *         add it to any other method if you don't exactly know what it does
     *
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
        if (count($gpxs)>0){
            foreach($gpxs as $gpx1){
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

        // PARAMS to send to template

        $params = [
            'python_error_output'=>$python_error_output,
            'python_return_var'=>$returnvar,
            'gpxs'=>$gpxs,
            'geojson'=>$geojson
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
     * Simply method that posts back the payload of the request
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function doEcho($echo) {
        return new DataResponse(['echo' => $echo]);
    }

    /**
     * Ajax geojson retrieval
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getgeo($title, $folder) {
        $userFolder = \OC::$server->getUserFolder();
        $file = $userFolder->get($folder.'/'.$title.'.geojson');
        $content = $file->getContent();
        $response = new DataResponse(
            [
                'track'=>$content
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
        $userFolder = \OC::$server->getUserFolder();
        $file = $userFolder->get($folder.'/'.$title.'.geojson.colored');
        $content = $file->getContent();
        $response = new DataResponse(
            [
                'track'=>$content
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

        // now considers encrypted storages
        // create a temp dir in cache, copy concerned files in clear version in this dir
        // then process the cached dir, then encrypt the results (newfile and putContent) and put them in the normal dir
        // then decrypt (normal getContent) all the markers files to return it as a result

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
            $gpsbabel_path = '';
            $path_ar = explode(':',getenv('path'));
            foreach ($path_ar as $path){
                $supposed_gpath = $path.'/gpsbabel';
                if (file_exists($supposed_gpath) and
                    is_executable($supposed_gpath)){
                    $gpsbabel_path = $supposed_gpath;
                }
            }

            if ($gpsbabel_path !== ''){
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

        // PROCESS gpx files and produce markers.txt

        $path_to_process = $data_folder.$subfolder;
        if ($userFolder->nodeExists($subfolder) and
            $userFolder->get($subfolder)->getType() == \OCP\Files\FileInfo::TYPE_FOLDER){
            // constraint on processtype
            // by default : process new files only
            // what we do :
            // we copy clear versions of the gpx files to the cache tmpdir
            // we process the clear versions
            // we copy back the geojson, geojson.colored and marker files
            // to the real dir

            // find gpxs
            $gpxfiles = Array();
            foreach ($userFolder->get($subfolder)->search(".gpx") as $ff){
                if ($ff->getType() == \OCP\Files\FileInfo::TYPE_FILE and
                    dirname($ff->getPath()) === $subfolder_path and
                    endswith($ff->getName(), '.gpx')
                ){
                    array_push($gpxfiles, $ff);
                }
            }
            foreach ($userFolder->get($subfolder)->search(".GPX") as $ff){
                if ($ff->getType() == \OCP\Files\FileInfo::TYPE_FILE and
                    dirname($ff->getPath()) === $subfolder_path and
                    endswith($ff->getName(), '.GPX')
                ){
                    array_push($gpxfiles, $ff);
                }
            }

            $processtype_arg = 'newonly';
            if ($scantype === 'all'){
                $processtype_arg = 'all';
                $gpxs_to_process = $gpxfiles;
            }
            else{
                $gpxs_to_process = Array();
                foreach($gpxfiles as $gg){
                    $gpx_relative_path = str_replace($userfolder_path, '', $gg->getPath());
                    $gpx_relative_path = trim($gpx_relative_path, '/');
                    $gpx_relative_path = str_replace('//', '/', $gpx_relative_path);
                    if (! $userFolder->nodeExists($gpx_relative_path.".geojson")){
                        array_push($gpxs_to_process, $gg);
                    }
                    else{
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
            exec(escapeshellcmd(
                $path_to_gpxpod.' '.escapeshellarg($clear_path_to_process)
                .' '.escapeshellarg($processtype_arg)
            ).' 2>&1',
            $output, $returnvar);

            // get results back to the real dir
            //$path_to_process_relative = str_replace($data_folder, '', $path_to_process);
            $geos = globRecursive($tempdir, '*.geojson', False);
            $geocs = globRecursive($tempdir, '*.geojson.colored', False);
            $mars = globRecursive($tempdir, '*.marker', False);
            $result_files = array_merge($geos, $geocs, $mars);
            foreach($result_files as $result_file_path){
                $clear_content = file_get_contents($result_file_path);
                $result_relative_path = $subfolder.'/'.basename($result_file_path);

                if ($userFolder->nodeExists($result_relative_path)){
                    $file = $userFolder->get($result_relative_path);
                }
                else{
                    $file = $userFolder->newFile($result_relative_path);
                }

                $file->putContent($clear_content);
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
        foreach ($userFolder->get($subfolder)->search(".marker") as $mf){
            if ($mf->getType() == \OCP\Files\FileInfo::TYPE_FILE and
                dirname($mf->getPath()) === $subfolder_path and
                endswith($mf->getName(), '.marker') and
                $userFolder->get($subfolder)->nodeExists(str_replace('.marker', '', $mf->getName()))
            ){
                $markercontent = $mf->getContent();
                $markertxt .= $markercontent;
                $markertxt .= ",";
            }
        }
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
     * Ajax python process kill
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function killpython($word) {
        $data_folder = $this->userAbsoluteDataPath;
        $command =
        "kill -9 `ps aux | grep python | grep ".escapeshellarg($data_folder)
        ." | awk '{print $2}'`".' 2>&1';
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
            $user = $_GET['user'];
            $path = $_GET['filepath'];
            $pathgeo = $path.'.geojson';
            $pathmarker = $path.'.marker';
            $uf = \OC::$server->getUserFolder($user);
            if ($uf->nodeExists($path) and $uf->nodeExists($pathgeo) and $uf->nodeExists($pathmarker)){
                $thefile = $uf->get($path);
                $thefilegeo = $uf->get($pathgeo);
                $thefilemarker = $uf->get($pathmarker);
                //error_log(\OCP\Constants::PERMISSION_DELETE." : ".($thefile->getPermissions() & \OCP\Constants::PERMISSION_DELETE));
                //error_log(\OCP\Share\IShare::getSharedWith($thefile));
                //$userUUID = ;
                $shares = $this->shareManager->getSharesBy($_GET['user'], \OCP\Share::SHARE_TYPE_LINK, $thefile, false, 1, 0);
                $sharesgeo = $this->shareManager->getSharesBy($_GET['user'], \OCP\Share::SHARE_TYPE_LINK, $thefilegeo, false, 1, 0);
                $sharesmarker = $this->shareManager->getSharesBy($_GET['user'], \OCP\Share::SHARE_TYPE_LINK, $thefilemarker, false, 1, 0);
                if (count($shares) > 0 and count($sharesgeo) > 0 and count($sharesmarker) > 0){
                    $allshares = array_merge($shares, $sharesgeo, $sharesmarker);
                    foreach($allshares as $share){
                        if ($share->getPassword() !== null){
                            return "This file is not a public share";
                        }
                    }
                    // gpx and geo and marker exist, they are shared with no password
                    $gpxcontent = $thefile->getContent();
                    $geocontent = $thefilegeo->getContent();
                    $markercontent = $thefilemarker->getContent();
                    $token = $shares[0]->getToken();
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
            'token'=>$token
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
