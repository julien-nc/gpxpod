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

    public function __construct($AppName, IRequest $request, $UserId, $userfolder, $config){
        parent::__construct($AppName, $request);
        $this->userId = $UserId;
        $this->userfolder = $userfolder;
        $this->config = $config;
        $this->userAbsoluteDataPath =
            $this->config->getSystemValue('datadirectory').
            $this->userfolder->getFullPath();
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
        $path_to_gpxpod = getcwd().'/apps/gpxpod/gpxpod.py';
        $subfolder = '';
        $gpxcomp_root_url = "gpxvcomp";

        // PROCESS

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

        // DIRS array population

        $dirs = Array();
        // use RecursiveDirectoryIterator if it exists in this environment
        if (class_exists('RecursiveDirectoryIterator')){
            $it = new \RecursiveDirectoryIterator($data_folder);
            $display = Array ('gpx');
            foreach(new \RecursiveIteratorIterator($it) as $file){
                if (in_array(strtolower(array_pop(explode('.', $file))), $display)){
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
            $files = globRecursive($data_folder, '*.gpx');
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
        $params = [
            'user' => $this->userId,
            'userAbsoluteDataPath'=>$this->userAbsoluteDataPath
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
        $params = [
            'user' => $this->userId,
            'userAbsoluteDataPath'=>$this->userAbsoluteDataPath
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
     *
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
     *
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
