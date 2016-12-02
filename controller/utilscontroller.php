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

class UtilsController extends Controller {


    private $userId;
    private $userfolder;
    private $config;
    private $userAbsoluteDataPath;
    private $absPathToGpxPod;
    private $dbconnection;
    private $dbtype;
    private $appPath;

    public function __construct($AppName, IRequest $request, $UserId, $userfolder, $config){
        parent::__construct($AppName, $request);
        $this->appPath = \OC_App::getAppPath('gpxpod');
        $this->userId = $UserId;
        $this->dbtype = $config->getSystemValue('dbtype');
        if ($this->dbtype === 'pgsql'){
            $this->dbdblquotes = '"';
        }
        else{
            $this->dbdblquotes = '';
        }
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
        // paths to python scripts
        $this->absPathToGpxPod = $this->appPath.'/gpxpod.py';
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
     * Delete all .geojson .geojson.colored and .marker files from
     * the owncloud filesystem because they are no longer usefull.
     * Usefull if they were created by gpxpod before v0.9.23 .
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
        $allNames = Array();
        foreach($types as $ext){
            $search = $userFolder->search($ext);
            foreach($search as $file){
                if (!in_array($file->getPath(), $allNames)){
                    array_push($all, $file);
                    array_push($allNames, $file->getPath());
                }
            }

        }
        $todel = Array();
        $problems = '<ul>';
        $deleted = '<ul>';
        foreach($all as $file){
            if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE){
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
     * Add one tile server to the DB for current user
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function addTileServer($servername, $serverurl) {
        // first we check it does not already exist
        $sqlts = 'SELECT servername FROM *PREFIX*gpxpod_tile_servers ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $sqlts .= 'AND servername=\''.$servername.'\' ';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $ts = null;
        while ($row = $req->fetch()){
            $ts = $row['servername'];
            break;
        }
        $req->closeCursor();

        // then if not, we insert it
        if ($ts === null){
            $sql = 'INSERT INTO *PREFIX*gpxpod_tile_servers';
            $sql .= ' ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', servername, url) ';
            $sql .= 'VALUES (\''.$this->userId.'\',';
            $sql .= '\''.$servername.'\',';
            $sql .= '\''.$serverurl.'\');';
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
     * Delete one tile server entry from DB for current user
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function deleteTileServer($servername) {
        $sqldel = 'DELETE FROM *PREFIX*gpxpod_tile_servers ';
        $sqldel .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' AND servername=\'';
        $sqldel .= $servername.'\';';
        //$sqldel .= 'WHERE user=\''.$this->userId.'\';';
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
     * Method to ask elevation correction on a single track.
     * gpxelevations (from SRTM.py) is called to do so in a temporary directory
     * then, the result track file is processed by gpxpod.py to
     * finally update the DB
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function processTrackElevations($trackname, $folder, $smooth) {
        $userFolder = \OC::$server->getUserFolder();
        $data_folder = $this->userAbsoluteDataPath;
        $gpxelePath = getProgramPath('gpxelevations');
        $path_to_gpxpod = $this->absPathToGpxPod;
        $success = False;

        $filerelpath = $folder.'/'.$trackname;

        if ($userFolder->nodeExists($filerelpath) and
            $userFolder->get($filerelpath)->getType() === \OCP\Files\FileInfo::TYPE_FILE and
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
            if ($returnvar === 0){
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
            exec(escapeshellcmd('python '.
                $path_to_gpxpod.' '.escapeshellarg($tempdir.'/')
                .' '.escapeshellarg('newonly')
            ).' 2>&1',
            $output, $returnvar);

            $result_gpx_path = $gpx_clear_path;
            $geo_path = $result_gpx_path.'.geojson';
            $geoc_path = $result_gpx_path.'.geojson.colored';
            $mar_path = $result_gpx_path.'.marker';
            $cleanFolder = $folder;
            if ($folder === '/'){
                $cleanFolder = '';
            }
            if (file_exists($geo_path) and file_exists($geoc_path) and file_exists($mar_path)){
                $gpx_relative_path = $cleanFolder.'/'.basename($result_gpx_path);
                $geo_content = str_replace("'", '"', file_get_contents($geo_path));
                $geoc_content = str_replace("'", '"', file_get_contents($geoc_path));
                $mar_content = str_replace("'", '"', file_get_contents($mar_path));

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
                    $success = True;
                }
                catch (Exception $e) {
                    error_log('Exception in Owncloud : '.$e->getMessage());
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

    /**
     * Save options values to the DB for current user
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function saveOptionsValues($optionsValues) {
        // first we check if user already has options values in DB
        $sqlts = 'SELECT jsonvalues FROM *PREFIX*gpxpod_options_values ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ';
        $req = $this->dbconnection->prepare($sqlts);
        $req->execute();
        $check = null;
        while ($row = $req->fetch()){
            $check = $row['jsonvalues'];
            break;
        }
        $req->closeCursor();

        // if nothing is there, we insert
        if ($check === null){
            $sql = 'INSERT INTO *PREFIX*gpxpod_options_values';
            $sql .= ' ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', jsonvalues) ';
            $sql .= 'VALUES (\''.$this->userId.'\',';
            $sql .= '\''.$optionsValues.'\');';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();
        }
        // else we update the values
        else{
            $sqlupd = 'UPDATE *PREFIX*gpxpod_options_values ';
            $sqlupd .= 'SET jsonvalues=\''.$optionsValues.'\' ';
            $sqlupd .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ; ';
            $req = $this->dbconnection->prepare($sqlupd);
            $req->execute();
            $req->closeCursor();
        }

        $response = new DataResponse(
            [
                'done'=>true
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
     * get options values to the DB for current user
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getOptionsValues($optionsValues) {
        $sqlov = 'SELECT jsonvalues FROM *PREFIX*gpxpod_options_values ';
        $sqlov .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'=\''.$this->userId.'\' ;';
        $req = $this->dbconnection->prepare($sqlov);
        $req->execute();
        $ov = '{}';
        while ($row = $req->fetch()){
            $ov = $row["jsonvalues"];
        }
        $req->closeCursor();

        $response = new DataResponse(
            [
                'values'=>$ov
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
