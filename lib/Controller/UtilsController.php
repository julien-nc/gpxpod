<?php
/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2015
 */

namespace OCA\GpxPod\Controller;

use OCA\GpxPod\AppInfo\Application;
use OCA\GpxPod\Service\ToolsService;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IConfig;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\DB\QueryBuilder\IQueryBuilder;

//require_once('utils.php');

class UtilsController extends Controller {
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IDBConnection
	 */
	private $dbconnection;
	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var mixed
	 */
	private $dbtype;
	/**
	 * @var string
	 */
	private $dbdblquotes;
	/**
	 * @var \OCP\Files\Folder
	 */
	private $userfolder;
	/**
	 * @var ToolsService
	 */
	private $toolsService;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								IRootFolder $root,
								IDBConnection $dbconnection,
								ToolsService $toolsService,
								?string $userId){
		parent::__construct($AppName, $request);
		$this->config = $config;
		$this->dbconnection = $dbconnection;
		$this->userId = $userId;
		$this->root = $root;
		$this->dbtype = $config->getSystemValue('dbtype');
		if ($this->dbtype === 'pgsql'){
			$this->dbdblquotes = '"';
		} else {
			$this->dbdblquotes = '';
		}
		if ($userId !== null && $userId !== ''){
			$this->userfolder = $this->root->getUserFolder($userId);
		}
		$this->toolsService = $toolsService;
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse('');
	}

	/**
	 * quote and choose string escape function depending on database used
	 */
	private function db_quote_escape_string(string $str): string {
		return $this->dbconnection->quote($str);
	}

	/**
	 * Delete all .geojson .geojson.colored and .marker files from
	 * the Nextcloud filesystem because they are no longer usefull.
	 * Usefull if they were created by gpxpod before v0.9.23 .
	 * @NoAdminRequired
	 */
	public function cleanMarkersAndGeojsons(string $forall): DataResponse {
		$del_all = ($forall === 'all');
		$userFolder = $this->userfolder;
		$userfolder_path = $userFolder->getPath();

		$types = ['.gpx.geojson', '.gpx.geojson.colored', '.gpx.marker'];
		$types_with_up = ['.gpx.geojson', '.gpx.geojson.colored', '.gpx.marker',
			'.GPX.geojson', '.GPX.geojson.colored', '.GPX.marker'];
		$all = [];
		$allNames = [];
		foreach ($types as $ext) {
			$search = $userFolder->search($ext);
			foreach ($search as $file) {
				if (!in_array($file->getPath(), $allNames)) {
					$all[] = $file;
					$allNames[] = $file->getPath();
				}
			}

		}
		$todel = [];
		$problems = '<ul>';
		$deleted = '<ul>';
		foreach ($all as $file) {
			if ($file->getType() === FileInfo::TYPE_FILE) {
				$name = $file->getName();
				foreach ($types_with_up as $ext) {
					if ($this->toolsService->endswith($name, $ext)) {
						$rel_path = str_replace($userfolder_path, '', $file->getPath());
						$rel_path = str_replace('//', '/', $rel_path);
						$gpx_rel_path = str_replace($ext, '.gpx', $rel_path);
						if ($del_all || $userFolder->nodeExists($gpx_rel_path)) {
							$todel[] = $file;
						}
					}
				}
			}
		}
		foreach ($todel as $ftd) {
			$rel_path = str_replace($userfolder_path, '', $ftd->getPath());
			$rel_path = str_replace('//', '/', $rel_path);
			if ($ftd->isDeletable()){
				$ftd->delete();
				$deleted .= '<li>'.$rel_path."</li>\n";
			} else {
				$problems .= '<li>Impossible to delete '.$rel_path."</li>\n";
			}
		}
		$problems .= '</ul>';
		$deleted .= '</ul>';

		$response = new DataResponse([
			'deleted' => $deleted,
			'problems' => $problems
		]);
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
	public function addTileServer($servername, $serverurl, $type, $token,
								  $layers, $version, $tformat, $opacity, $transparent,
								  $minzoom, $maxzoom, $attribution): DataResponse {
		// first we check it does not already exist
		$sqlts = '
            SELECT servername
            FROM *PREFIX*gpxpod_tile_servers
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND servername='.$this->db_quote_escape_string($servername).'
                  AND type='.$this->db_quote_escape_string($type).' ;';
		$req = $this->dbconnection->prepare($sqlts);
		$req->execute();
		$ts = null;
		while ($row = $req->fetch()) {
			$ts = $row['servername'];
			break;
		}
		$req->closeCursor();

		// then if not, we insert it
		if ($ts === null) {
			$sql = '
                INSERT INTO *PREFIX*gpxpod_tile_servers
                ('.$this->dbdblquotes.'user'.$this->dbdblquotes.', type, servername, url, token, layers, version, format, opacity, transparent, minzoom, maxzoom, attribution)
                VALUES ('.
				$this->db_quote_escape_string($this->userId).','.
				$this->db_quote_escape_string($type).','.
				$this->db_quote_escape_string($servername).','.
				$this->db_quote_escape_string($serverurl).','.
				$this->db_quote_escape_string($token).','.
				$this->db_quote_escape_string($layers).','.
				$this->db_quote_escape_string($version).','.
				$this->db_quote_escape_string($tformat).','.
				$this->db_quote_escape_string($opacity).','.
				$this->db_quote_escape_string($transparent).','.
				$this->db_quote_escape_string($minzoom).','.
				$this->db_quote_escape_string($maxzoom).','.
				$this->db_quote_escape_string($attribution).'
                ) ;';
			$req = $this->dbconnection->prepare($sql);
			$req->execute();
			$req->closeCursor();
			$ok = 1;
		} else{
			$ok = 0;
		}

		$response = new DataResponse([
			'done' => $ok
		]);
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
	public function deleteTileServer($servername, $type): DataResponse {
		$sqldel = '
            DELETE FROM *PREFIX*gpxpod_tile_servers
            WHERE '.$this->dbdblquotes.'user'.$this->dbdblquotes.'='.$this->db_quote_escape_string($this->userId).'
                  AND servername='.$this->db_quote_escape_string($servername).'
                  AND type='.$this->db_quote_escape_string($type).' ;';
		$req = $this->dbconnection->prepare($sqldel);
		$req->execute();
		$req->closeCursor();

		$response = new DataResponse([
			'done' => 1
		]);
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
	public function saveOptionValue($key, $value): DataResponse {
		if (is_bool($value)) {
			$value = $value ? 'true' : 'false';
		}
		$this->config->setUserValue($this->userId, 'gpxpod', $key, $value);

		$response = new DataResponse([
			'done' => true
		]);
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
	public function saveOptionValues(array $values): DataResponse {
		foreach ($values as $key => $value) {
			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			}
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}

		return new DataResponse('');
	}

	/**
	 * get options values to the DB for current user
	 * @NoAdminRequired
	 */
	public function getOptionsValues(): DataResponse {
		$ov = [];
		$keys = $this->config->getUserKeys($this->userId, 'gpxpod');
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($this->userId, 'gpxpod', $key);
			$ov[$key] = $value;
		}

		$response = new DataResponse([
			'values' => $ov
		]);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * Delete user options
	 * @NoAdminRequired
	 */
	public function deleteOptionsValues(): DataResponse {
		$keys = $this->config->getUserKeys($this->userId, 'gpxpod');
		foreach ($keys as $key) {
			$this->config->deleteUserValue($this->userId, 'gpxpod', $key);
		}

		$response = new DataResponse([
			'done' => 1
		]);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function moveTracks($trackpaths, $destination): DataResponse {
		$uf = \OC::$server->getUserFolder($this->userId);
		$done = False;
		$moved = '';
		$notmoved = '';
		$message = '';
		$cleanDest = str_replace(array('../', '..\\'), '', $destination);

		if ($uf->nodeExists($cleanDest)){
			$destNode = $uf->get($cleanDest);
			if ($destNode->getType() === FileInfo::TYPE_FOLDER
				&& $destNode->isCreatable()
			) {
				$done = True;
				foreach ($trackpaths as $path) {
					$cleanPath = str_replace(array('../', '..\\'), '', $path);
					if ($uf->nodeExists($cleanPath)) {
						$file = $uf->get($cleanPath);
						// everything ok, we move
						if (!$destNode->nodeExists($file->getName())) {
							$file->move($uf->getPath().'/'.$cleanDest.'/'.$file->getName());
							$moved .= $cleanPath.', ';
						} else {
							// destination file already exists
							$notmoved .= $cleanPath.', ';
							$message .= 'de ';
						}
					} else {
						$notmoved .= $cleanPath.', ';
						$message .= 'one ';
					}
				}
			} else {
				// dest not writable
				$message .= 'dnw ';
			}
		} else {
			// dest does not exist
			$message .= 'dne ';
		}

		$moved = rtrim($moved, ', ');
		$notmoved = rtrim($notmoved, ', ');

		$response = new DataResponse([
			'message' => $message,
			'moved' => $moved,
			'notmoved' => $notmoved,
			'done' => $done
		]);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * Empty track DB for current user
	 * @NoAdminRequired
	 */
	public function cleanDb(): DataResponse {
		$qb = $this->dbconnection->getQueryBuilder();
		$userId = $this->userId;

		$qb->delete('gpxpod_tracks')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$qb->delete('gpxpod_directories')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$qb->delete('gpxpod_pictures')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		$response = new DataResponse([
			'done' => 1
		]);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}
}
