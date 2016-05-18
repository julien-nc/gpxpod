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
        $userFolder = \OC::$server->getUserFolder();
        $data_folder = $this->userAbsoluteDataPath;
        $folder = str_replace(array('../', '..\\'), '',  $folder);
        $folder_relative = str_replace($data_folder, '', $folder);
        $file = $userFolder->get($folder_relative.'/'.$title.'.geojson');
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
        // TODO adapt
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

        // TODO consider encrypted storages
        // idea : create a temp dir in cache, copy concerned files in clear version in this dir
        // then process the cached dir, then encrypt the results (newfile and putContent) and put them in the normal dir
        // then decrypt (normal getContent) all the markers files to return it as a result
        //
        $userFolder = \OC::$server->getUserFolder();
        $file = $userFolder->get('/eee.txt');
        error_log($file->getContent());
        error_log($file->isEncrypted());
        $file2 = $userFolder->newFile('gpx/hehe.txt');
        $file2->putContent("plophehe");
        error_log($file2->getContent());

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
        $kmls = globRecursive($path_to_process, '*.kml', False);
        $kmlms = globRecursive($path_to_process, '*.KML', False);
        foreach($kmlms as $kk){
            array_push($kmls, $kk);
        }

        $tcxs = globRecursive($path_to_process, '*.tcx', False);
        $tcxms = globRecursive($path_to_process, '*.TCX', False);
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
                            // we read content, then write it in the tempdir
                            // then convert, then read content then write it back in
                            // the real dir
                            $kml_relative_path = str_replace($data_folder, '', $kml);
                            $gpx_relative_path = dirname($kml_relative_path).'/'.basename($gpx_target);
                            $kmlfile = $userFolder->get($kml_relative_path);
                            $kmlcontent = $kmlfile->getContent();
                            $kml_clear_path = $tempdir.'/'.basename($kml);
                            $gpx_target_clear_path = $tempdir.'/'.basename($gpx_target);
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
                            $gpx_file = $userFolder->newFile($gpx_relative_path);
                            $gpx_file->putContent($gpx_clear_content);
                        }
                    }
                }
                foreach($tcxs as $tcx){
                    if(dirname($tcx) === $path_to_process){
                        $gpx_target = str_replace('.tcx', '.gpx', $tcx);
                        $gpx_target = str_replace('.TCX', '.gpx', $gpx_target);
                        if (!file_exists($gpx_target)){
                            // we read content, then write it in the tempdir
                            // then convert, then read content then write it back in
                            // the real dir
                            $tcx_relative_path = str_replace($data_folder, '', $tcx);
                            $gpx_relative_path = dirname($tcx_relative_path).'/'.basename($gpx_target);
                            $tcxfile = $userFolder->get($tcx_relative_path);
                            $tcxcontent = $tcxfile->getContent();
                            $tcx_clear_path = $tempdir.'/'.basename($tcx);
                            $gpx_target_clear_path = $tempdir.'/'.basename($gpx_target);
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
                            $gpx_file = $userFolder->newFile($gpx_relative_path);
                            $gpx_file->putContent($gpx_clear_content);
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
            // what we do :
            // we copy clear versions of the gpx files to the cache tmpdir
            // we process the clear versions
            // we copy back the geojson, geojson.colored and marker files
            // to the real dir

            // find gpxs
            $gpxs = globRecursive($path_to_process, '*.gpx', False);
            $gpxms = globRecursive($path_to_process, '*.GPX', False);
            foreach($gpxms as $gg){
                array_push($gpxs, $gg);
            }

            $processtype_arg = 'newonly';
            if ($scantype === 'all'){
                $processtype_arg = 'all';
                $gpxs_to_process = $gpxs;
            }
            else{
                $gpxs_to_process = Array();
                foreach($gpxs as $gg){
                    if (! file_exists($gg.'.geojson')){
                        array_push($gpxs_to_process, $gg);
                    }
                }
            }
            // copy files
            foreach($gpxs_to_process as $gpx){
                $gpx_relative_path = str_replace($data_folder, '', $gpx);
                $gpxfile = $userFolder->get($gpx_relative_path);
                $gpxcontent = $gpxfile->getContent();
                $gpx_clear_path = $tempdir.'/'.basename($gpx);
                file_put_contents($gpx_clear_path, $gpxcontent);
            }

            $clear_path_to_process = $tempdir.'/';
            exec(escapeshellcmd(
                $path_to_gpxpod.' '.escapeshellarg($clear_path_to_process)
                .' '.escapeshellarg($processtype_arg)
            ).' 2>&1',
            $output, $returnvar);

            // get results back to the real dir
            $path_to_process_relative = str_replace($data_folder, '', $path_to_process);
            $geos = globRecursive($tempdir, '*.geojson', False);
            $geocs = globRecursive($tempdir, '*.geojson.colored', False);
            $mars = globRecursive($tempdir, '*.marker', False);
            $result_files = array_merge($geos, $geocs, $mars);
            foreach($result_files as $result_file_path){
                $clear_content = file_get_contents($result_file_path);
                $result_relative_path = $path_to_process_relative.'/'.basename($result_file_path);
                $file = $userFolder->newFile($result_relative_path);
                $file->putContent($clear_content);
            }
            // TODO delete tmpdir
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
        $path_to_process_relative = str_replace($data_folder, '', $path_to_process);
        $markerfiles = globRecursive($path_to_process, '*.marker', False);
        $markertxt = "{\"markers\" : [";
        foreach($markerfiles as $mf){
            $marker_relative_path = $path_to_process_relative.'/'.basename($mf);
            $markerfile = $userFolder->get($marker_relative_path);
            $markercontent = $markerfile->getContent();

            $markertxt .= $markercontent;
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
