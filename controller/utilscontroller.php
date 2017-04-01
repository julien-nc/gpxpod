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
    $path_ar = array_merge($path_ar, explode(':',getenv('PATH')));
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

    public function __construct($AppName, IRequest $request, $UserId,
        $userfolder, $config, IAppManager $appManager){
        parent::__construct($AppName, $request);
        // just to keep Owncloud compatibility
        // the first case : Nextcloud
        // else : Owncloud
        if (method_exists($appManager, 'getAppPath')){
            $this->appPath = $appManager->getAppPath('gpxpod');
        }
        else {
            $this->appPath = \OC_App::getAppPath('gpxpod');
            // even dirtier
            //$this->appPath = getcwd().'/apps/gpxpod';
        }
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

    /*
     * quote and choose string escape function depending on database used
     */
    private function db_quote_escape_string($str){
        return $this->dbconnection->quote($str);
    }

    /**
     * Delete all .geojson .geojson.colored and .marker files from
     * the owncloud filesystem because they are no longer usefull.
     * Usefull if they were created by gpxpod before v0.9.23 .
     * @NoAdminRequired
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
     */
    public function addTileServer($servername, $serverurl) {
        // first we check it does not already exist
        $sqlts = 'SELECT servername FROM *PREFIX*gpxpod_tile_servers ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
        $sqlts .= 'AND servername='.$this->db_quote_escape_string($servername).' ';
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
            $sql .= 'VALUES ('.$this->db_quote_escape_string($this->userId).',';
            $sql .= ''.$this->db_quote_escape_string($servername).',';
            $sql .= ''.$this->db_quote_escape_string($serverurl).');';
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
     */
    public function deleteTileServer($servername) {
        $sqldel = 'DELETE FROM *PREFIX*gpxpod_tile_servers ';
        $sqldel .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' AND servername=';
        $sqldel .= $this->db_quote_escape_string($servername).';';
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
     * Save options values to the DB for current user
     * @NoAdminRequired
     */
    public function saveOptionsValues($optionsValues) {
        // first we check if user already has options values in DB
        $sqlts = 'SELECT jsonvalues FROM *PREFIX*gpxpod_options_values ';
        $sqlts .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ';
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
            $sql .= 'VALUES ('.$this->db_quote_escape_string($this->userId).',';
            $sql .= ''.$this->db_quote_escape_string($optionsValues).');';
            $req = $this->dbconnection->prepare($sql);
            $req->execute();
            $req->closeCursor();
        }
        // else we update the values
        else{
            $sqlupd = 'UPDATE *PREFIX*gpxpod_options_values ';
            $sqlupd .= 'SET jsonvalues='.$this->db_quote_escape_string($optionsValues).' ';
            $sqlupd .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ; ';
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
     */
    public function getOptionsValues($optionsValues) {
        $sqlov = 'SELECT jsonvalues FROM *PREFIX*gpxpod_options_values ';
        $sqlov .= 'WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).' ;';
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
