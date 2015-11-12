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
            $this->userfolder->getFullPath();
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
        $kmls = Array();
        // use RecursiveDirectoryIterator if it exists in this environment
        if (class_exists('RecursiveDirectoryIterator')){
            $it = new \RecursiveDirectoryIterator($data_folder);
            $display = Array ('gpx','kml');
            foreach(new \RecursiveIteratorIterator($it) as $file){
                $ext = strtolower(array_pop(explode('.', $file)));
                if (in_array($ext, $display)){
                    // populate kml array
                    if ($ext === 'kml'){
                        array_push($kmls, $file);
                    }

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
            function globRecursive($path, $find) {
                $dh = opendir($path);
                while (($file = readdir($dh)) !== false) {
                    if (substr($file, 0, 1) == '.') continue;
                    $rfile = "{$path}/{$file}";
                    if (is_dir($rfile)) {
                        foreach (globRecursive($rfile, $find) as $ret) {
                            yield $ret;
                        }
                    } else {
                        if (fnmatch($find, $file)) yield $rfile;
                    }
                }
                closedir($dh);
            }
            $gpxs = globRecursive($data_folder, '*.gpx');
            $kmls = globRecursive($data_folder, '*.kml');
            $files = array_merge($gpxs, $kmls);
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

        // Convert KML to GPX
        // only if we want to display a folder AND it exists AND we want
        // to compute AND we find GPSBABEL AND file was not already converted

        if (!empty($_GET)){
            $subfolder = str_replace(array('../', '..\\'), '',
                $_GET['subfolder']);
            if ($subfolder === '/'){
                $subfolder = '';
            }
            $path_to_process = $data_folder.$subfolder;
            if (file_exists($path_to_process) and
                is_dir($path_to_process)){
                if (!isset($_GET['computecheck']) or
                    $_GET['computecheck'] === 'no'){
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
                    }
                }
            }
        }

        // PROCESS gpx files and produce markers.txt

        if (!empty($_GET)){
            //$subfolder = str_replace(array('/', '\\'), '',  $_GET['subfolder']);
            $subfolder = str_replace(array('../', '..\\'), '',  $_GET['subfolder']);
            $path_to_process = $data_folder.$subfolder;
            if (file_exists($path_to_process) and is_dir($path_to_process)){
                // then we process the folder if it was asked
                if (!isset($_GET['computecheck']) or $_GET['computecheck'] === 'no'){
                    exec(escapeshellcmd(
                        $path_to_gpxpod.' '.escapeshellarg($path_to_process)
                    ),
                    $output, $returnvar);
                }
            }
            else{
                //die($path_to_process.' does not exist');
            }
        }

        // info for JS

        $markers_txt = '';
        if ($subfolder !== ''){
            $markers_txt = file_get_contents($path_to_process.'/markers.txt');
        }

        // PARAMS to view

        $params = [
            'dirs'=>$dirs,
            'subfolder'=>$subfolder,
            'rooturl'=>$rooturl,
            'gpxcomp_root_url'=>$gpxcomp_root_url,
            'markers_txt'=>$markers_txt
        ];
        $response = new TemplateResponse('gpxpod', 'main', $params);
        $csp = new ContentSecurityPolicy();
        $csp->addAllowedImageDomain('*')
            ->addAllowedMediaDomain('*')
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
            foreach($gpxs as $gpx){
                $geojson[$gpx] = file_get_contents($gpx.'.geojson');
                unlink($gpx.'.geojson');
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
            foreach($gpxs as $gpx){
                $geojson[$gpx] = file_get_contents($gpx.'.geojson');
                unlink($gpx.'.geojson');
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

}
