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

function globRecursive($path, $find) {
    $result = Array();
    $dh = opendir($path);
    while (($file = readdir($dh)) !== false) {
        if (substr($file, 0, 1) == '.') continue;
        $rfile = "{$path}/{$file}";
        if (is_dir($rfile)) {
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

class PageController extends Controller {


    private $userId;
    private $userfolder;
    private $config;
    private $userAbsoluteDataPath;
    private $absPathToGpxvcomp;
    private $absPathToGpxPod;

    public function __construct($AppName, IRequest $request, $UserId, $userfolder, $config){
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        // path of user files folder relative to DATA folder
        $this->userfolder = $userfolder;
        // IConfig object
        $this->config = $config;
        // absolute path to user files folder
        $this->userAbsoluteDataPath =
            $this->config->getSystemValue('datadirectory').
            rtrim($this->userfolder->getFullPath(''), '/');
        //error_log(rtrim($this->userfolder->getFullPath(''), '/'));
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
        $data_folder = $this->userAbsoluteDataPath;
        $path_to_gpxpod = $this->absPathToGpxPod;
        $subfolder = '';
        $gpxcomp_root_url = "gpxvcomp";

        // DIRS array population

        $dirs = Array();
        // use RecursiveDirectoryIterator if it exists in this environment
        if (class_exists('RecursiveDirectoryIterator')){
            $it = new \RecursiveDirectoryIterator($data_folder);
            $display = Array ('gpx','kml','GPX','KML','tcx','TCX');
            foreach(new \RecursiveIteratorIterator($it) as $file){
                $exp = explode('.', $file);
                $ext = array_pop($exp);
                $ext = strtolower($ext);
                if (in_array($ext, $display)){
                    $dir = str_replace($data_folder,'',dirname($file));
                    if ($dir === ''){
                        $dir = '/';
                    }
                    if (!in_array($dir, $dirs)){
                        array_push($dirs, $dir);
                    }
                }
            }
        }
        // if no RecursiveDirectoryIterator was found, use recursive glob method
        else{

            $gpxs = globRecursive($data_folder, '*.gpx');
            $gpxms = globRecursive($data_folder, '*.GPX');
            $kmls = globRecursive($data_folder, '*.kml');
            $kmlms = globRecursive($data_folder, '*.KML');
            $tcxs = globRecursive($data_folder, '*.tcx');
            $tcxms = globRecursive($data_folder, '*.TCX');
            $files = Array();
            foreach($tcxms as $gg){
                array_push($files, $gg);
            }
            foreach($tcxs as $kk){
                array_push($files, $kk);
            }
            foreach($gpxms as $gg){
                array_push($files, $gg);
            }
            foreach($kmlms as $kk){
                array_push($files, $kk);
            }
            foreach($gpxs as $gg){
                array_push($files, $gg);
            }
            foreach($kmls as $kk){
                array_push($files, $kk);
            }
            foreach($files as $file){
                $dir = str_replace($data_folder,'',dirname($file));
                if ($dir === ''){
                    $dir = '/';
                }
                if (!in_array($dir, $dirs)){
                    array_push($dirs, $dir);
                }
            }
        }


        // PARAMS to view

        sort($dirs);
        $params = [
            'dirs'=>$dirs,
            'gpxcomp_root_url'=>$gpxcomp_root_url
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
                    file_put_contents($tempdir.'/'.$name, file_get_contents($data_folder
                        .$subfolder.'/'.$name));
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
        $data_folder = $this->userAbsoluteDataPath;
        $folder = str_replace(array('../', '..\\'), '',  $folder);
        $response = new DataResponse(
            [
                'track'=>file_get_contents(
                    "$data_folder$folder/$title.geojson"
                )
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
        $data_folder = $this->userAbsoluteDataPath;
        $folder = str_replace(array('../', '..\\'), '',  $folder);
        $response = new DataResponse(
            [
                'track'=>file_get_contents(
                    "$data_folder$folder/$title.geojson.colored"
                )
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
        $data_folder = $this->userAbsoluteDataPath;
        $subfolder = str_replace(array('../', '..\\'), '',  $subfolder);

        $path_to_gpxpod = $this->absPathToGpxPod;

        // Convert KML to GPX
        // only if we want to display a folder AND it exists AND we want
        // to compute AND we find GPSBABEL AND file was not already converted

        if ($subfolder === '/'){
            $subfolder = '';
        }
        $path_to_process = $data_folder.$subfolder;

        // find kmls
        $kmls = globRecursive($path_to_process, '*.kml');
        $kmlms = globRecursive($path_to_process, '*.KML');
        foreach($kmlms as $kk){
            array_push($kmls, $kk);
        }

        $tcxs = globRecursive($path_to_process, '*.tcx');
        $tcxms = globRecursive($path_to_process, '*.TCX');
        foreach($tcxms as $kk){
            array_push($tcxs, $kk);
        }

        // convert kmls
        if (file_exists($path_to_process) and
            is_dir($path_to_process)){
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
                foreach($kmls as $kml){
                    if(dirname($kml) === $path_to_process){
                        $gpx_target = str_replace('.kml', '.gpx', $kml);
                        $gpx_target = str_replace('.KML', '.gpx', $gpx_target);
                        if (!file_exists($gpx_target)){
                            $args = Array('-i', 'kml', '-f', $kml, '-o',
                                'gpx', '-F', $gpx_target);
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
                        }
                    }
                }
                foreach($tcxs as $tcx){
                    if(dirname($tcx) === $path_to_process){
                        $gpx_target = str_replace('.tcx', '.gpx', $tcx);
                        $gpx_target = str_replace('.TCX', '.gpx', $gpx_target);
                        if (!file_exists($gpx_target)){
                            $args = Array('-i', 'gtrnctr', '-f', $tcx, '-o',
                                'gpx', '-F', $gpx_target);
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
                        }
                    }
                }
            }
        }

        // PROCESS gpx files and produce markers.txt

        $path_to_process = $data_folder.$subfolder;
        if (file_exists($path_to_process) and is_dir($path_to_process)){
            // constraint on processtype
            // by default : process new files only
            $processtype_arg = 'newonly';
            if ($scantype === 'all'){
                $processtype_arg = 'all';
            }
            exec(escapeshellcmd(
                $path_to_gpxpod.' '.escapeshellarg($path_to_process)
                .' '.escapeshellarg($processtype_arg)
            ).' 2>&1',
            $output, $returnvar);
        }
        else{
            //die($path_to_process.' does not exist');
        }

        // PROCESS error management

        $python_error_output = null;
        $python_error_output_cleaned = array();
        if (file_exists($path_to_process) and
            is_dir($path_to_process)){
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
        $markerfiles = globRecursive($path_to_process, '*.marker');
        $markertxt = "{\"markers\" : [";
        foreach($markerfiles as $mf){
            $markertxt .= file_get_contents($mf);
            $markertxt .= ",";
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

}
