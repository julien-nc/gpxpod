<?php
/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2015
 */

namespace OCA\GpxPod\Controller;

use OCA\GpxPod\AppInfo\Application;
use OCA\GpxPod\Db\TileServerMapper;
use OCA\GpxPod\Service\ToolsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\FileInfo;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;

class UtilsController extends Controller {

	public function __construct($appName,
		IRequest $request,
		private IConfig $config,
		private IRootFolder $root,
		private IDBConnection $db,
		private ToolsService $toolsService,
		private TileServerMapper $tileServerMapper,
		private ?string $userId) {
		parent::__construct($appName, $request);
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
	 * Delete all .geojson .geojson.colored and .marker files from
	 * the Nextcloud filesystem because they are no longer usefull.
	 * Usefull if they were created by gpxpod before v0.9.23 .
	 * @NoAdminRequired
	 */
	public function cleanMarkersAndGeojsons(string $forall): DataResponse {
		$del_all = ($forall === 'all');
		$userFolder = $this->root->getUserFolder($this->userId);
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
					if (str_ends_with($name, $ext)) {
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
			if ($ftd->isDeletable()) {
				$ftd->delete();
				$deleted .= '<li>'.$rel_path."</li>\n";
			} else {
				$problems .= '<li>Impossible to delete '.$rel_path."</li>\n";
			}
		}
		$problems .= '</ul>';
		$deleted .= '</ul>';

		return new DataResponse([
			'deleted' => $deleted,
			'problems' => $problems,
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $type
	 * @param string $name
	 * @param string $url
	 * @param string|null $attribution
	 * @param int|null $min_zoom
	 * @param int|null $max_zoom
	 * @return DataResponse
	 */
	public function addTileServer(int $type, string $name, string $url, ?string $attribution = null,
		?int $min_zoom = null, ?int $max_zoom = null): DataResponse {
		try {
			$tileServer = $this->tileServerMapper->createTileServer($this->userId, $type, $name, $url, $attribution, $min_zoom, $max_zoom);
			return new DataResponse($tileServer);
		} catch (\OCP\DB\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @return DataResponse
	 */
	public function deleteTileServer(int $id): DataResponse {
		try {
			$this->tileServerMapper->deleteTileserver($id, $this->userId);
			return new DataResponse(1);
		} catch (\OCP\DB\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $type
	 * @param string $name
	 * @param string $url
	 * @param string|null $attribution
	 * @param int|null $min_zoom
	 * @param int|null $max_zoom
	 * @return DataResponse
	 */
	public function adminAddTileServer(int $type, string $name, string $url, ?string $attribution = null,
		?int $min_zoom = null, ?int $max_zoom = null): DataResponse {
		try {
			$tileServer = $this->tileServerMapper->createTileServer(null, $type, $name, $url, $attribution, $min_zoom, $max_zoom);
			return new DataResponse($tileServer);
		} catch (\OCP\DB\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @param int $id
	 * @return DataResponse
	 */
	public function adminDeleteTileServer(int $id): DataResponse {
		try {
			$this->tileServerMapper->deleteTileserver($id, null);
			return new DataResponse(1);
		} catch (\OCP\DB\Exception $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * Add one tile server to the DB for current user
	 *
	 * @param string $servername
	 * @param string $serverurl
	 * @param string $type
	 * @param string|null $token
	 * @param string|null $layers
	 * @param string|null $version
	 * @param string|null $tformat
	 * @param string|null $opacity
	 * @param bool|null $transparent
	 * @param int|null $minzoom
	 * @param int|null $maxzoom
	 * @param string|null $attribution
	 * @return DataResponse
	 * @throws Exception
	 */
	public function oldAddTileServer(string $servername, string $serverurl, string $type, ?string $token = null,
		?string $layers = null, ?string $version = null, ?string $tformat = null,
		?string $opacity = null, ?bool $transparent = null,
		?int $minzoom = null, ?int $maxzoom = null, ?string $attribution = null): DataResponse {
		$qb = $this->db->getQueryBuilder();
		// first we check it does not already exist
		// is the project shared with the user ?
		$qb->select('servername')
			->from('gpxpod_tile_servers')
			->where($qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('servername', $qb->createNamedParameter($servername, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR)));
		$req = $qb->executeQuery();
		$ts = null;
		while ($row = $req->fetch()) {
			$ts = $row['servername'];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// then if not, we insert it
		if ($ts === null) {
			$values = [
				'user' => $qb->createNamedParameter($this->userId),
				'type' => $qb->createNamedParameter($type),
				'servername' => $qb->createNamedParameter($servername),
				'url' => $qb->createNamedParameter($serverurl),
			];
			if ($transparent !== null) {
				$values['transparent'] = $qb->createNamedParameter($transparent ? 'true' : 'false');
			}
			$optionalColumns = [
				'token' => ['value' => $token, 'type' => IQueryBuilder::PARAM_STR],
				'layers' => ['value' => $layers, 'type' => IQueryBuilder::PARAM_STR],
				'version' => ['value' => $version, 'type' => IQueryBuilder::PARAM_STR],
				'format' => ['value' => $tformat, 'type' => IQueryBuilder::PARAM_STR],
				'opacity' => ['value' => $opacity, 'type' => IQueryBuilder::PARAM_STR],
				'minzoom' => ['value' => $minzoom, 'type' => IQueryBuilder::PARAM_INT],
				'maxzoom' => ['value' => $maxzoom, 'type' => IQueryBuilder::PARAM_INT],
				'attribution' => ['value' => $attribution, 'type' => IQueryBuilder::PARAM_STR],
			];
			foreach ($optionalColumns as $key => $column) {
				if ($column['value'] !== null) {
					$values[$key] = $qb->createNamedParameter($column['value'], $column['type']);
				}
			}
			$qb->insert('gpxpod_tile_servers')
				->values($values);
			$qb->executeStatement();
			$qb = $qb->resetQueryParts();

			$ok = 1;
		} else {
			$ok = 0;
		}

		return new DataResponse([
			'done' => $ok,
		]);
	}

	/**
	 * Delete one tile server entry from DB for current user
	 * @NoAdminRequired
	 */
	public function oldDeleteTileServer(string $servername, string $type): DataResponse {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('gpxpod_tile_servers')
			->where($qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('servername', $qb->createNamedParameter($servername, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
		$qb = $qb->resetQueryParts();

		return new DataResponse([
			'done' => 1,
		]);
	}

	/**
	 * Save options values to the DB for current user
	 * @NoAdminRequired
	 */
	public function saveOptionValue($key, $value): DataResponse {
		if (is_bool($value)) {
			$value = $value ? 'true' : 'false';
		}
		$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);

		return new DataResponse([
			'done' => true,
		]);
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
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			$ov[$key] = $value;
		}

		return new DataResponse([
			'values' => $ov,
		]);
	}

	/**
	 * Delete user options
	 * @NoAdminRequired
	 */
	public function deleteOptionsValues(): DataResponse {
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$this->config->deleteUserValue($this->userId, Application::APP_ID, $key);
		}

		return new DataResponse([
			'done' => 1,
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function moveTracks($trackpaths, $destination): DataResponse {
		$uf = $this->root->getUserFolder($this->userId);
		$done = false;
		$moved = '';
		$notmoved = '';
		$message = '';
		$cleanDest = str_replace(['../', '..\\'], '', $destination);

		if ($uf->nodeExists($cleanDest)) {
			$destNode = $uf->get($cleanDest);
			if ($destNode instanceof Folder
				&& $destNode->isCreatable()
			) {
				$done = true;
				foreach ($trackpaths as $path) {
					$cleanPath = str_replace(['../', '..\\'], '', $path);
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

		return new DataResponse([
			'message' => $message,
			'moved' => $moved,
			'notmoved' => $notmoved,
			'done' => $done,
		]);
	}

	/**
	 * Empty track DB for current user
	 * @NoAdminRequired
	 */
	public function cleanDb(): DataResponse {
		$qb = $this->db->getQueryBuilder();
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

		return new DataResponse([
			'done' => 1,
		]);
	}
}
