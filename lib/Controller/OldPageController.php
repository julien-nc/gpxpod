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

use OCA\GpxPod\Service\ConversionService;
use OCA\GpxPod\Service\ProcessService;
use OCA\GpxPod\Service\ToolsService;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IDBConnection;
use OCP\IConfig;
use \OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\Share\IManager;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCP\DB\QueryBuilder\IQueryBuilder;

//require_once('utils.php');

/**
 * Self contained old controller including all it needs
 * TODO delete this controller once everything is reimplemented in a nicer way
 */
class OldPageController extends Controller {

	private $userfolder;
	private $userId;
	private $config;
	private $shareManager;
	private $dbconnection;
	private $extensions;
	private $logger;
	private $trans;
	private $upperExtensions;
	private $gpxpodCachePath;
	protected $appName;
	/**
	 * @var IRootFolder
	 */
	private $root;
	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;
	/**
	 * @var ConversionService
	 */
	private $conversionService;
	/**
	 * @var ProcessService
	 */
	private $processService;
	/**
	 * @var ToolsService
	 */
	private $toolsService;

	public function __construct($AppName,
								IRequest $request,
								IConfig $config,
								IManager $shareManager,
								LoggerInterface $logger,
								IL10N $trans,
								IInitialState $initialStateService,
								IRootFolder $root,
								IDBConnection $dbconnection,
								IClientService $clientService,
								ConversionService $conversionService,
								ProcessService $processService,
								ToolsService $toolsService,
								?string $userId) {
		parent::__construct($AppName, $request);
		$this->logger = $logger;
		$this->trans = $trans;
		$this->initialStateService = $initialStateService;
		$this->appName = $AppName;
		$this->userId = $userId;
		$this->root = $root;
		$this->client = $clientService->newClient();
		if ($userId !== null && $userId !== ''){
			$this->userfolder = $this->root->getUserFolder($userId);
		}
		$this->config = $config;
		$this->dbconnection = $dbconnection;
		$this->gpxpodCachePath = $this->config->getSystemValue('datadirectory').'/gpxpod';
		if (!is_dir($this->gpxpodCachePath)) {
			mkdir($this->gpxpodCachePath);
		}
		$this->shareManager = $shareManager;

		$this->extensions = [
			'.kml' => 'kml',
			'.gpx' => '',
			'.tcx' => 'gtrnctr',
			'.igc' => 'igc',
			'.jpg' => '',
			'.fit' => 'garmin_fit',
		];
		$this->upperExtensions = array_map('strtoupper', array_keys($this->extensions));
		$this->conversionService = $conversionService;
		$this->processService = $processService;
		$this->toolsService = $toolsService;
	}

	private function getUserTileServers(string $type, string $username = '', string $layername = ''): array {
		$qb = $this->dbconnection->getQueryBuilder();
		$user = $username;
		if ($user === '') {
			$user = $this->userId;
		}
		$tss = [];
		// custom tile servers management
		$qb->select('servername', 'type', 'url', 'layers', 'version', 'token',
			'format', 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution')
			->from('gpxpod_tile_servers', 'ts')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($user, IQueryBuilder::PARAM_STR))
			);

		// if username is set, we filter anyway
		if ($username !== '') {
			if ($type === 'tile' || $type === 'mapboxtile' || $type === 'tilewms') {
				$qb->andWhere(
					$qb->expr()->eq('servername', $qb->createNamedParameter($layername, IQueryBuilder::PARAM_STR))
				);
			} else if ($layername !== '') {
				$servers = explode(';;', $layername);

				$or = $qb->expr()->orx();
				foreach ($servers as $server) {
					$or->add($qb->expr()->eq('servername', $qb->createNamedParameter($server, IQueryBuilder::PARAM_STR)));
				}
				$qb->andWhere($or);
			} else {
				$qb = $qb->resetQueryParts();
				return [];
			}
		}
		$qb->andWhere(
			$qb->expr()->eq('type', $qb->createNamedParameter($type, IQueryBuilder::PARAM_STR))
		);
		$req = $qb->execute();

		while ($row = $req->fetch()) {
			$tss[$row['servername']] = [];
			foreach (['servername', 'type', 'url', 'layers', 'version', 'format', 'token',
						 'opacity', 'transparent', 'minzoom', 'maxzoom', 'attribution'] as $field) {
				$tss[$row['servername']][$field] = $row[$field];
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();
		return $tss;
	}

	private function resetTrackDbBy304() {
		$alreadyDone = $this->config->getAppValue('gpxpod', 'reset304');
		if ($alreadyDone !== '1') {
			$qb = $this->dbconnection->getQueryBuilder();
			$qb->delete('gpxpod_tracks');
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();

			$this->config->setAppValue('gpxpod', 'reset304', '1');
		}
	}

	private function resetPicturesDbBy404() {
		$alreadyDone = $this->config->getAppValue('gpxpod', 'resetPics404');
		if ($alreadyDone !== '1') {
			$qb = $this->dbconnection->getQueryBuilder();
			$qb->delete('gpxpod_pictures');
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();

			$this->config->setAppValue('gpxpod', 'resetPics404', '1');
		}
	}

	/**
	 * Welcome page.
	 * Get list of interesting folders (containing gpx/kml/tcx files)
	 * Determine if "gpxelevations" is found to give extra scan options
	 * to the view.
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		$this->initialStateService->provideInitialState(
			'photos',
			$this->config->getAppValue('photos', 'enabled', 'no') === 'yes'
		);
		$userFolder = $this->userfolder;
		$userfolder_path = $userFolder->getPath();
		$gpxcomp_root_url = 'gpxvcomp';
		$gpxedit_version = $this->config->getAppValue('gpxedit', 'installed_version');
		$gpxmotion_version = $this->config->getAppValue('gpxmotion', 'installed_version');

		$this->cleanDbFromAbsentFiles(null);

		$this->resetTrackDbBy304();
		$this->resetPicturesDbBy404();

		$alldirs = $this->getDirectories($this->userId);

		$gpxelePath = $this->toolsService->getProgramPath('gpxelevations');
		$hassrtm = false;
		if ($gpxelePath !== null) {
			$hassrtm = true;
		}

		$tss = $this->getUserTileServers('tile');
		$mbtss = $this->getUserTileServers('mapboxtile');
		$oss = $this->getUserTileServers('overlay');
		$tssw = $this->getUserTileServers('tilewms');
		$ossw = $this->getUserTileServers('overlaywms');

		$extraSymbolList = $this->getExtraSymbolList();

		// PARAMS to view

//		natcasesort($alldirs);
		$dirs = array_map(static function(array $dir): string {
			return $dir['path'];
		}, $alldirs);
		require_once('tileservers.php');
		$params = [
			'dirs' => $dirs,
			'gpxcomp_root_url' => $gpxcomp_root_url,
			'username' => $this->userId,
			'hassrtm' => $hassrtm,
			'basetileservers' => $baseTileServers,
			'usertileservers' => $tss,
			'usermapboxtileservers' => $mbtss,
			'useroverlayservers' => $oss,
			'usertileserverswms' => $tssw,
			'useroverlayserverswms' => $ossw,
			'publicgpx' => '',
			'publicmarker' => '',
			'publicdir' => '',
			'pictures' => '',
			'token' => '',
			'gpxedit_version' => $gpxedit_version,
			'gpxmotion_version' => $gpxmotion_version,
			'extrasymbols' => $extraSymbolList,
			'gpxpod_version' => $this->config->getAppValue('gpxpod', 'installed_version'),
		];
		$response = new TemplateResponse('gpxpod', 'main', $params);
		$response->addHeader("Access-Control-Allow-Origin", "*");
		$csp = new ContentSecurityPolicy();
		$csp->allowInlineScript()
			->allowEvalScript()
			->allowInlineStyle()
			->addAllowedScriptDomain('*')
			->addAllowedStyleDomain('*')
			->addAllowedFontDomain('*')
			->addAllowedImageDomain('*')
			->addAllowedConnectDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedObjectDomain('*')
			->addAllowedFrameDomain('*')
			->addAllowedChildSrcDomain("* blob:");
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * returns extra symbol names found in gpxedit data
	 */
	private function getExtraSymbolList() {
		// extra symbols
		$gpxEditDataDirPath = $this->config->getSystemValue('datadirectory').'/gpxedit';
		$extraSymbolList = [];
		if (is_dir($gpxEditDataDirPath.'/symbols')) {
			foreach($this->toolsService->globRecursive($gpxEditDataDirPath.'/symbols', '*.png', False) as $symbolfile) {
				$filename = basename($symbolfile);
				$extraSymbolList[] = ['smallname' => str_replace('.png', '', $filename), 'name' => $filename];
			}
		}
		return $extraSymbolList;
	}

	/**
	 * @param string $userId
	 * @param string $path
	 * @return array|null
	 * @throws \OCP\DB\Exception
	 */
	private function getDirectoryByPath(string $userId, string $path): ?array {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id', 'path', 'user', 'is_open')
			->from('gpxpod_directories')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('path', $qb->createNamedParameter($path, IQueryBuilder::PARAM_STR))
			);

		$req = $qb->execute();
		$directory = null;
		while ($row = $req->fetch()) {
			$directory = [
				'id' => (int)$row['id'],
				'path' => $row['path'],
				'user' => $row['user'],
				'is_open' => (int)$row['is_open'] === 1,
			];
			break;
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		return $directory;
	}

	/**
	 * @NoAdminRequired
	 */
	public function delDirectory(string $path): DataResponse {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->delete('gpxpod_directories')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->eq('path', $qb->createNamedParameter($path, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		$qb = $qb->resetQueryParts();

		// delete track metadata from DB
		$trackpathToDelete = [];

		$qb->select('trackpath', 'marker')
			->from('gpxpod_tracks', 't')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->like('trackpath', $qb->createNamedParameter($path.'%', IQueryBuilder::PARAM_STR))
			);

		$req = $qb->execute();

		while ($row = $req->fetch()) {
			if (dirname($row['trackpath']) === $path) {
				$trackpathToDelete[] = $row['trackpath'];
			}
		}

		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		foreach ($trackpathToDelete as $trackpath) {
			$qb->delete('gpxpod_tracks')
				->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('trackpath', $qb->createNamedParameter($trackpath, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}

		return new DataResponse('DONE');
	}

	public function getDirectories(string $userId): array {
		$qb = $this->dbconnection->getQueryBuilder();
		$qb->select('id', 'path', 'is_open')
			->from('gpxpod_directories', 'd')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);

		$req = $qb->execute();

		$dirs = [];
		while ($row = $req->fetch()) {
			$dirs[] = [
				'path' => $row['path'],
				'id' => (int) $row['id'],
				'is_open' => (int) $row['is_open'] === 1,
			];
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		return $dirs;
	}

	/**
	 * Ajax gpx retrieval
	 * @NoAdminRequired
	 */
	public function getgpx($path) {
		$userFolder = $this->userfolder;

		$cleanpath = str_replace(['../', '..\\'], '',  $path);
		$gpxContent = '';
		if ($userFolder->nodeExists($cleanpath)) {
			$file = $userFolder->get($cleanpath);
			if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
				if ($this->toolsService->endswith($file->getName(), '.GPX') || $this->toolsService->endswith($file->getName(), '.gpx')) {
					$gpxContent = $this->toolsService->remove_utf8_bom($file->getContent());
				}
			}
		}

		$response = new DataResponse(
			[
				'content' => $gpxContent
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
	 * Ajax gpx retrieval
	 * @NoAdminRequired
	 * @PublicPage
	 */
	public function getpublicgpx($path, $username) {
		$userFolder = $this->root->getUserFolder($username);

		$cleanpath = str_replace(['../', '..\\'], '',  $path);
		$gpxContent = '';
		if ($userFolder->nodeExists($cleanpath)) {
			$file = $userFolder->get($cleanpath);

			if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
				if ($this->toolsService->endswith($file->getName(), '.GPX') || $this->toolsService->endswith($file->getName(), '.gpx')) {
					// we check the file is actually shared by public link
					$dl_url = $this->getPublinkDownloadURL($file, $username);

					if ($dl_url !== null) {
						$gpxContent = $this->toolsService->remove_utf8_bom($file->getContent());
					}
				}
			}
		}

		$response = new DataResponse(
			[
				'content' => $gpxContent
			]
		);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getTrackMarkersText(string $directoryPath, bool $processAll = false, bool $recursive = false) {
		$userFolder = $this->userfolder;
		$qb = $this->dbconnection->getQueryBuilder();

		if ($directoryPath === null || !$userFolder->nodeExists($directoryPath) || $this->getDirectoryByPath($this->userId, $directoryPath) === null) {
			return new DataResponse('No such directory', 400);
		}

		$subfolder_path = $userFolder->get($directoryPath)->getPath();

		$optionValues = $this->getSharedMountedOptionValue();
		$sharedAllowed = $optionValues['sharedAllowed'];
		$mountedAllowed = $optionValues['mountedAllowed'];

		// Convert KML to GPX
		// only if we want to display a folder AND it exists AND we want
		// to compute AND we find GPSBABEL AND file was not already converted

		if ($directoryPath === '/') {
			$directoryPath = '';
		}

		$filesByExtension = [];
		foreach($this->extensions as $ext => $gpsbabel_fmt) {
			$filesByExtension[$ext] = [];
		}

		if (!$recursive) {
			foreach ($userFolder->get($directoryPath)->getDirectoryListing() as $ff) {
				if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
					$ffext = '.'.strtolower(pathinfo($ff->getName(), PATHINFO_EXTENSION));
					if (in_array( $ffext, array_keys($this->extensions))) {
						// if shared files are allowed or it is not shared
						if ($sharedAllowed || !$ff->isShared()) {
							$filesByExtension[$ffext][] = $ff;
						}
					}
				}
			}
		} else {
			$showpicsonlyfold = $this->config->getUserValue($this->userId, 'gpxpod', 'showpicsonlyfold', 'true');
			$searchJpg = ($showpicsonlyfold === 'true');
			$extensions = array_keys($this->extensions);
			if ($searchJpg) {
				$extensions = array_merge($extensions, ['.jpg']);
			}
			$files = $this->processService->searchFilesWithExt($userFolder->get($directoryPath), $sharedAllowed, $mountedAllowed, $extensions);
			foreach ($files as $file) {
				$fileext = '.'.strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
				if ($sharedAllowed || !$file->isShared()) {
					$filesByExtension[$fileext][] = $file;
				}
			}
		}

		$this->convertFiles($userFolder, $directoryPath, $this->userId, $filesByExtension);

		// PROCESS gpx files and fill DB
		$this->processGpxFiles($userFolder, $directoryPath, $this->userId, $recursive, $sharedAllowed, $mountedAllowed, $processAll);

		// PROCESS error management

		// info for JS

		// build markers
		$subfolder_sql = $directoryPath;
		if ($directoryPath === '') {
			$subfolder_sql = '/';
		}
		$markertxt = '{"markers" : {';
		// DB style
		$qb->select('id', 'trackpath', 'marker')
			->from('gpxpod_tracks', 't')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->like('trackpath', $qb->createNamedParameter($subfolder_sql.'%', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();

		while ($row = $req->fetch()) {
			if ($recursive || dirname($row['trackpath']) === $subfolder_sql) {
				// if the gpx file exists
				if ($userFolder->nodeExists($row['trackpath'])) {
					$ff = $userFolder->get($row['trackpath']);
					// if it's a file, if shared files are allowed or it's not shared
					if (    $ff->getType() === \OCP\Files\FileInfo::TYPE_FILE
						&& ($sharedAllowed || !$ff->isShared())
					) {
						$markertxt .= '"'.$row['id'] . '": ' . $row['marker'];
						$markertxt .= ',';
					}
				}
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// CLEANUP DB for non-existing files
		$this->cleanDbFromAbsentFiles($directoryPath);

		$markertxt = rtrim($markertxt, ',');
		$markertxt .= '}}';

		$pictures_json_txt = $this->getGeoPicsFromFolder($directoryPath, $recursive);

		$response = new DataResponse(
			[
				'markers' => $markertxt,
				'pictures' => $pictures_json_txt,
				'error' => ''
			]
		);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	private function processGpxFiles(Folder $userFolder, string $subfolder, string $userId, bool $recursive, bool $sharedAllowed, bool $mountedAllowed, bool $processAll) {
		if ($userFolder->nodeExists($subfolder) &&
			$userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {

			// get the dir ID
			$directory = $this->getDirectoryByPath($userId, $subfolder);

			$userfolder_path = $userFolder->getPath();
			$qb = $this->dbconnection->getQueryBuilder();
			// find gpxs db style
			$gpxs_in_db = [];
			$qb->select('trackpath', 'contenthash')
				->from('gpxpod_tracks', 't')
				->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->execute();
			while ($row = $req->fetch()) {
				$gpxs_in_db[$row['trackpath']] = $row['contenthash'];
			}
			$req->closeCursor();
			$qb = $qb->resetQueryParts();


			// find gpxs
			$gpxfiles = [];

			if (!$recursive) {
				foreach ($userFolder->get($subfolder)->getDirectoryListing() as $ff) {
					if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
						$ffext = '.'.strtolower(pathinfo($ff->getName(), PATHINFO_EXTENSION));
						if ($ffext === '.gpx') {
							// if shared files are allowed or it is not shared
							if ($sharedAllowed || !$ff->isShared()) {
								$gpxfiles[] = $ff;
							}
						}
					}
				}
			} else {
				$gpxfiles = $this->processService->searchFilesWithExt($userFolder->get($subfolder), $sharedAllowed, $mountedAllowed, ['.gpx']);
			}

			// CHECK what is to be processed
			$gpxs_to_process = [];
			$newCRC = [];
			foreach ($gpxfiles as $gg) {
				$gpx_relative_path = str_replace($userfolder_path, '', $gg->getPath());
				$gpx_relative_path = rtrim($gpx_relative_path, '/');
				$gpx_relative_path = str_replace('//', '/', $gpx_relative_path);
				$newCRC[$gpx_relative_path] = $gg->getMTime().'.'.$gg->getSize();
				// if the file is not in the DB or if its content hash has changed
				if ((! array_key_exists($gpx_relative_path, $gpxs_in_db)) or
					$gpxs_in_db[$gpx_relative_path] !== $newCRC[$gpx_relative_path] or
					$processAll === 'true'
				) {
					// not in DB or hash changed
					$gpxs_to_process[] = $gg;
				}
			}

			$markers = $this->processService->getMarkersFromFiles($gpxs_to_process, $userId);

			// DB STYLE
			foreach ($markers as $trackpath => $marker) {
				$gpx_relative_path = str_replace($userfolder_path, '', $trackpath);
				$gpx_relative_path = rtrim($gpx_relative_path, '/');
				$gpx_relative_path = str_replace('//', '/', $gpx_relative_path);

				if (! array_key_exists($gpx_relative_path, $gpxs_in_db)) {
					$qb->insert('gpxpod_tracks')
						->values([
							'user' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
							'trackpath' => $qb->createNamedParameter($gpx_relative_path, IQueryBuilder::PARAM_STR),
							'contenthash' => $qb->createNamedParameter($newCRC[$gpx_relative_path], IQueryBuilder::PARAM_STR),
							'marker' => $qb->createNamedParameter($marker, IQueryBuilder::PARAM_STR),
							'directory_id' => $qb->createNamedParameter($directory['id'], IQueryBuilder::PARAM_INT),
						]);
					$req = $qb->execute();
					$qb = $qb->resetQueryParts();
				} else {
					$qb->update('gpxpod_tracks');
					$qb->set('marker', $qb->createNamedParameter($marker, IQueryBuilder::PARAM_STR));
					$qb->set('contenthash', $qb->createNamedParameter($newCRC[$gpx_relative_path], IQueryBuilder::PARAM_STR));
					$qb->where(
						$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
					);
					$qb->andWhere(
						$qb->expr()->eq('trackpath', $qb->createNamedParameter($gpx_relative_path, IQueryBuilder::PARAM_STR))
					);
					$req = $qb->execute();
					$qb = $qb->resetQueryParts();
				}
			}
		}
	}

	private function convertFiles($userFolder, $subfolder, $userId, $filesByExtension) {
		// convert kml, tcx etc...
		if (    $userFolder->nodeExists($subfolder)
			&& $userFolder->get($subfolder)->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {

			$gpsbabel_path = $this->toolsService->getProgramPath('gpsbabel');
			$igctrack = $this->config->getUserValue($userId, 'gpxpod', 'igctrack');

			if ($gpsbabel_path !== null) {
				foreach ($this->extensions as $ext => $gpsbabel_fmt) {
					if ($ext !== '.gpx' && $ext !== '.jpg') {
						$igcfilter1 = '';
						$igcfilter2 = '';
						if ($ext === '.igc') {
							if ($igctrack === 'pres') {
								$igcfilter1 = '-x';
								$igcfilter2 = 'track,name=PRESALTTRK';
							} elseif ($igctrack === 'gnss') {
								$igcfilter1 = '-x';
								$igcfilter2 = 'track,name=GNSSALTTRK';
							}
						}
						foreach ($filesByExtension[$ext] as $f) {
							$name = $f->getName();
							$gpx_targetname = str_replace($ext, '.gpx', $name);
							$gpx_targetname = str_replace(strtoupper($ext), '.gpx', $gpx_targetname);
							$gpx_targetfolder = $f->getParent();
							if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
								// we read content, then launch the command, then write content on stdin
								// then read gpsbabel stdout then write it in a NC file
								$content = $f->getContent();

								if ($igcfilter1 !== '') {
									$args = ['-i', $gpsbabel_fmt, '-f', '-',
										$igcfilter1, $igcfilter2, '-o',
										'gpx', '-F', '-'];
								} else {
									$args = ['-i', $gpsbabel_fmt, '-f', '-',
										'-o', 'gpx', '-F', '-'];
								}
								$cmdparams = '';
								foreach ($args as $arg) {
									$shella = escapeshellarg($arg);
									$cmdparams .= " $shella";
								}
								$descriptorspec = [
									0 => ['pipe', 'r'],
									1 => ['pipe', 'w'],
									2 => ['pipe', 'w']
								];
								$process = proc_open(
									$gpsbabel_path.' '.$cmdparams,
									$descriptorspec,
									$pipes
								);
								// write to stdin
								fwrite($pipes[0], $content);
								fclose($pipes[0]);
								// read from stdout
								$gpx_clear_content = stream_get_contents($pipes[1]);
								fclose($pipes[1]);
								// read from stderr
								$stderr = stream_get_contents($pipes[2]);
								fclose($pipes[2]);

								$return_value = proc_close($process);

								// write result in NC files
								$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
								$gpx_file->putContent($gpx_clear_content);
							}
						}
					}
				}
			} else {
				// Fallback for igc without GpsBabel
				foreach ($filesByExtension['.igc'] as $f) {
					$name = $f->getName();
					$gpx_targetname = str_replace(['.igc', '.IGC'], '.gpx', $name);
					$gpx_targetfolder = $f->getParent();
					if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
						$fdesc = $f->fopen('r');
						$gpx_clear_content = $this->conversionService->igcToGpx($fdesc, $igctrack);
						fclose($fdesc);
						$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
						$gpx_file->putContent($gpx_clear_content);
					}
				}
				// Fallback KML conversion without GpsBabel
				foreach ($filesByExtension['.kml'] as $f) {
					$name = $f->getName();
					$gpx_targetname = str_replace(['.kml', '.KML'], '.gpx', $name);
					$gpx_targetfolder = $f->getParent();
					if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
						$content = $f->getContent();
						$gpx_clear_content = $this->conversionService->kmlToGpx($content);
						$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
						$gpx_file->putContent($gpx_clear_content);
					}
				}
				// Fallback TCX conversion without GpsBabel
				foreach ($filesByExtension['.tcx'] as $f) {
					$name = $f->getName();
					$gpx_targetname = str_replace(['.tcx', '.TCX'], '.gpx', $name);
					$gpx_targetfolder = $f->getParent();
					if (! $gpx_targetfolder->nodeExists($gpx_targetname)) {
						$content = $f->getContent();
						$gpx_clear_content = $this->conversionService->tcxToGpx($content);
						$gpx_file = $gpx_targetfolder->newFile($gpx_targetname);
						$gpx_file->putContent($gpx_clear_content);
					}
				}
			}
		}
	}

	/**
	 * Method to ask elevation correction on a single track.
	 * gpxelevations (from SRTM.py) is called to do so in a temporary directory
	 * then, the result track file is processed to
	 * finally update the DB
	 * @NoAdminRequired
	 */
	public function processTrackElevations($path, $smooth) {
		$userFolder = $this->root->getUserFolder($this->userId);
		$qb = $this->dbconnection->getQueryBuilder();
		$gpxelePath = $this->toolsService->getProgramPath('gpxelevations');
		$success = False;
		$message = '';

		$filerelpath = $path;
		$folderPath = dirname($path);

		if ($userFolder->nodeExists($filerelpath) and
			$userFolder->get($filerelpath)->getType() === \OCP\Files\FileInfo::TYPE_FILE and
			$gpxelePath !== null
		) {
			// srtmification
			$gpxfile = $userFolder->get($filerelpath);
			$gpxfilename = $gpxfile->getName();
			$gpxcontent = $gpxfile->getContent();

			$osmooth = '';
			if ($smooth === 'true') {
				$osmooth = '-s';
			}

			// tricky, isn't it ? as gpxelevations wants to read AND write in files,
			// we use BASH process substitution to make it read from STDIN
			// and write to cat which writes to STDOUT, then we filter to only keep what we want and VOILA
			$cmd = 'bash -c "export HOMEPATH=\''.$this->gpxpodCachePath.'\' ; export HOME=\''.$this->gpxpodCachePath.'\' ; '.$gpxelePath.' <(cat -) '.$osmooth.' -o -f >(cat -) 1>&2 "';

			$descriptorspec = [
				0 => ['pipe', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w']
			];
			// srtm.py (used by gpxelevations) needs HOME or HOMEPATH
			// to be set to store cache data
			$process = proc_open(
				$cmd,
				$descriptorspec,
				$pipes
			);
			// write to stdin
			fwrite($pipes[0], $gpxcontent);
			fclose($pipes[0]);
			// read from stdout
			$res_content = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			// read from stderr
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[2]);

			$return_value = proc_close($process);

			$subfolderobj = $userFolder->get($folderPath);
			// overwrite original gpx files with corrected ones
			if ($return_value === 0) {
				$correctedName = str_replace(['.gpx', '.GPX'], '_corrected.gpx', $gpxfilename);
				if ($subfolderobj->nodeExists($correctedName)) {
					$of = $subfolderobj->get($correctedName);
					if ($of->getType() === \OCP\Files\FileInfo::TYPE_FILE and
						$of->isUpdateable()) {
						$of->putContent($res_content);
					}
				} elseif ($subfolderobj->getType() === \OCP\Files\FileInfo::TYPE_FOLDER
					&& $subfolderobj->isCreatable()) {
					$subfolderobj->newFile($correctedName);
					$subfolderobj->get($correctedName)->putContent($res_content);
				}
			} else {
				$message = $this->trans->t('There was an error during "gpxelevations" execution on the server');
				$this->logger->error('There was an error during "gpxelevations" execution on the server : '. $stderr, ['app' => $this->appName]);
			}

			// PROCESS

			if ($return_value === 0) {
				$mar_content = $this->processService->getMarkerFromFile($subfolderobj->get($correctedName), $this->userId);
			}

			$cleanFolder = $folderPath;
			if ($folderPath === '/') {
				$cleanFolder = '';
			}
			// in case it does not exists, the following query won't have any effect
			if ($return_value === 0) {
				$gpx_relative_path = $cleanFolder.'/'.$correctedName;

				$qb->update('gpxpod_tracks');
				$qb->set('marker', $qb->createNamedParameter($mar_content, IQueryBuilder::PARAM_STR));
				$qb->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
				)
					->andWhere(
						$qb->expr()->eq('trackpath', $qb->createNamedParameter($gpx_relative_path, IQueryBuilder::PARAM_STR))
					);
				$req = $qb->execute();
				$qb = $qb->resetQueryParts();

				$success = True;
			}
		}

		$response = new DataResponse(
			[
				'done' => $success,
				'message' => $message
			]
		);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	private function getSharedMountedOptionValue($uid=null) {
		$userId = $uid;
		if ($uid === null) {
			$userId = $this->userId;
		}
		// get option values
		$ss = $this->config->getUserValue($userId, 'gpxpod', 'showshared', 'true');
		$sm = $this->config->getUserValue($userId, 'gpxpod', 'showmounted', 'true');
		$sharedAllowed = ($ss === 'true');
		$mountedAllowed = ($sm === 'true');
		return ['sharedAllowed' => $sharedAllowed, 'mountedAllowed' => $mountedAllowed];
	}

	/**
	 * get list of geolocated pictures in $subfolder with coordinates
	 * first copy the pics to a temp dir
	 * then get the pic list and coords with gpsbabel
	 */
	private function getGeoPicsFromFolder($subfolder, $recursive, $user=null) {
		if (!function_exists('exif_read_data')) {
			return '{}';
		}

		$pictures_json_txt = '{';

		$userId = $user;
		// if user is not given, the request comes from connected user threw getmarkers
		if ($user === null) {
			$userFolder = $this->userfolder;
			$userId = $this->userId;
		} else {
			// else, it comes from a public dir
			$userFolder = $this->root->getUserFolder($user);
		}
		$subfolder = str_replace(['../', '..\\'], '', $subfolder);
		$subfolder_path = $userFolder->get($subfolder)->getPath();
		$userfolder_path = $userFolder->getPath();
		$qb = $this->dbconnection->getQueryBuilder();

		$imagickAvailable = class_exists('Imagick');

		$optionValues = $this->getSharedMountedOptionValue($user);
		$sharedAllowed = $optionValues['sharedAllowed'];
		$mountedAllowed = $optionValues['mountedAllowed'];

		// get picture files
		$picfiles = [];
		if ($recursive) {
			$picfiles = $this->processService->searchFilesWithExt($userFolder->get($subfolder), $sharedAllowed, $mountedAllowed, ['.jpg']);
		} else {
			foreach ($userFolder->get($subfolder)->search('.jpg') as $picfile) {
				if ($picfile->getType() === \OCP\Files\FileInfo::TYPE_FILE
					&& dirname($picfile->getPath()) === $subfolder_path
					&& (
						$this->toolsService->endswith($picfile->getName(), '.jpg')
						|| $this->toolsService->endswith($picfile->getName(), '.JPG')
					)
				) {
					$picfiles[] = $picfile;
				}
			}
		}
		// get list of paths to manage deletion of absent files
		$picpaths = [];
		foreach ($picfiles as $picfile) {
			$pic_relative_path = str_replace($userfolder_path, '', $picfile->getPath());
			$pic_relative_path = rtrim($pic_relative_path, '/');
			$pic_relative_path = str_replace('//', '/', $pic_relative_path);
			$picpaths[] = $pic_relative_path;
		}

		$dbToDelete = [];
		// get what's in the DB
		$dbPicsWithCoords = [];
		$qb->select('path', 'contenthash')
			->from('gpxpod_pictures', 'p')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNotNull('lat')
			)
			->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder.'%', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();

		$gpxs_in_db = [];
		while ($row = $req->fetch()) {
			$dbPicsWithCoords[$row['path']] = $row['contenthash'];
			if ($recursive) {
				if (!in_array($row['path'], $picpaths)) {
					$dbToDelete[] = $row['path'];
				}
			} else {
				if (dirname($row['path']) === $subfolder
					&& !in_array($row['path'], $picpaths)
				) {
					$dbToDelete[] = $row['path'];
				}
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// get non-geotagged pictures
		$dbPicsWithoutCoords = [];
		$qb->select('path', 'contenthash')
			->from('gpxpod_pictures', 'p')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNull('lat')
			)
			->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder.'%', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();

		$gpxs_in_db = [];
		while ($row = $req->fetch()) {
			$dbPicsWithoutCoords[$row['path']] = $row['contenthash'];
			if ($recursive) {
				if (!in_array($row['path'], $picpaths)) {
					$dbToDelete[] = $row['path'];
				}
			} elseif (dirname($row['path']) === $subfolder
				&& !in_array($row['path'], $picpaths)) {
				$dbToDelete[] = $row['path'];
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		// CHECK what is to be processed
		$picfilesToProcess = [];
		$newCRC = [];
		foreach ($picfiles as $pp) {
			$pic_relative_path = str_replace($userfolder_path, '', $pp->getPath());
			$pic_relative_path = rtrim($pic_relative_path, '/');
			$pic_relative_path = str_replace('//', '/', $pic_relative_path);
			$newCRC[$pic_relative_path] = $pp->getMTime().'.'.$pp->getSize();
			// if the file is not in the DB or if its content hash has changed
			if ((! array_key_exists($pic_relative_path, $dbPicsWithCoords))
				&& (! array_key_exists($pic_relative_path, $dbPicsWithoutCoords))
			) {
				$picfilesToProcess[] = $pp;
			} elseif (array_key_exists($pic_relative_path, $dbPicsWithCoords)
				&& $dbPicsWithCoords[$pic_relative_path] !== $newCRC[$pic_relative_path]
			) {
				$picfilesToProcess[] = $pp;
			} elseif (array_key_exists($pic_relative_path, $dbPicsWithoutCoords)
				&& $dbPicsWithoutCoords[$pic_relative_path] !== $newCRC[$pic_relative_path]
			) {
				$picfilesToProcess[] = $pp;
			} elseif (array_key_exists($pic_relative_path, $dbPicsWithoutCoords)) {
				//error_log('NOOOOT '.$pic_relative_path);
			}
		}

		// get coordinates of each picture file
		foreach ($picfilesToProcess as $picfile) {
			try {
				$lat = null;
				$lon = null;
				$dateTaken = null;

				// first we try with php exif function
				$filePath = $picfile->getStorage()->getLocalFile($picfile->getInternalPath());
				$exif = @exif_read_data($filePath, 'GPS,EXIF', true);
				if (    isset($exif['GPS'])
					&& isset($exif['GPS']['GPSLongitude'])
					&& isset($exif['GPS']['GPSLatitude'])
					&& isset($exif['GPS']['GPSLatitudeRef'])
					&& isset($exif['GPS']['GPSLongitudeRef'])
				) {
					$lon = $this->conversionService->getDecimalCoords($exif['GPS']['GPSLongitude'], $exif['GPS']['GPSLongitudeRef']);
					$lat = $this->conversionService->getDecimalCoords($exif['GPS']['GPSLatitude'], $exif['GPS']['GPSLatitudeRef']);
					// then get date
					if (isset($exif['EXIF']) && isset($exif['EXIF']['DateTimeOriginal'])) {
						$dateTaken = strtotime($exif['EXIF']['DateTimeOriginal']);
					}
				}
				// if no lat/lng were found, we try with imagick if available
				if ($lat === null && $lon === null && $imagickAvailable) {
					$pfile = $picfile->fopen('r');
					$img = new \Imagick();
					$img->readImageFile($pfile);
					$allGpsProp = $img->getImageProperties('exif:GPS*');
					if (    isset($allGpsProp['exif:GPSLatitude'])
						&& isset($allGpsProp['exif:GPSLongitude'])
						&& isset($allGpsProp['exif:GPSLatitudeRef'])
						&& isset($allGpsProp['exif:GPSLongitudeRef'])
					) {
						$lon = $this->conversionService->getDecimalCoords(explode(', ', $allGpsProp['exif:GPSLongitude']), $allGpsProp['exif:GPSLongitudeRef']);
						$lat = $this->conversionService->getDecimalCoords(explode(', ', $allGpsProp['exif:GPSLatitude']), $allGpsProp['exif:GPSLatitudeRef']);
						// then get date
						$dateProp = $img->getImageProperties('exif:DateTimeOriginal');
						if (isset($dateProp['exif:DateTimeOriginal'])) {
							$dateTaken = strtotime($dateProp['exif:DateTimeOriginal']);
						}
					}
					fclose($pfile);
				}

				// insert/update the DB
				$pic_relative_path = str_replace($userfolder_path, '', $picfile->getPath());
				$pic_relative_path = rtrim($pic_relative_path, '/');
				$pic_relative_path = str_replace('//', '/', $pic_relative_path);

				if (! array_key_exists($pic_relative_path, $dbPicsWithCoords)
					&& ! array_key_exists($pic_relative_path, $dbPicsWithoutCoords)
				) {
					$qb->insert('gpxpod_pictures')
						->values([
							'user' => $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
							'path' => $qb->createNamedParameter($pic_relative_path, IQueryBuilder::PARAM_STR),
							'contenthash' => $qb->createNamedParameter($newCRC[$pic_relative_path], IQueryBuilder::PARAM_STR),
							'lat' => $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR),
							'lon' => $qb->createNamedParameter($lon, IQueryBuilder::PARAM_STR),
							'date_taken' => $qb->createNamedParameter($dateTaken, IQueryBuilder::PARAM_INT)
						]);
					$req = $qb->execute();
					$qb = $qb->resetQueryParts();
				} else {
					$qb->update('gpxpod_pictures');
					$qb->set('lat', $qb->createNamedParameter($lat, IQueryBuilder::PARAM_STR));
					$qb->set('lon', $qb->createNamedParameter($lon, IQueryBuilder::PARAM_STR));
					$qb->set('date_taken', $qb->createNamedParameter($dateTaken, IQueryBuilder::PARAM_INT));
					$qb->set('contenthash', $qb->createNamedParameter($newCRC[$pic_relative_path], IQueryBuilder::PARAM_STR));
					$qb->where(
						$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
					)
						->andWhere(
							$qb->expr()->eq('path', $qb->createNamedParameter($pic_relative_path, IQueryBuilder::PARAM_STR))
						);
					$req = $qb->execute();
					$qb = $qb->resetQueryParts();
				}
			}
			catch (\Exception $e) {
				$this->logger->error(
					'Exception in picture geolocation reading for file '.$picfile->getPath().' : '. $e->getMessage(),
					['app' => $this->appName]
				);
			}
		}

		// build result data from DB
		$subfolder_sql = $subfolder;
		if ($subfolder === '') {
			$subfolder_sql = '/';
		}
		$qb->select('path', 'lat', 'lon', 'date_taken')
			->from('gpxpod_pictures', 'p')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			->andWhere(
				$qb->expr()->isNotNull('lat')
			)
			->andWhere(
				$qb->expr()->isNotNull('lon')
			)
			->andWhere(
				$qb->expr()->like('path', $qb->createNamedParameter($subfolder_sql.'%', IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			if ($recursive || dirname($row['path']) === $subfolder_sql) {
				// if the pic file exists
				if ($userFolder->nodeExists($row['path'])) {
					$ff = $userFolder->get($row['path']);
					// if it's a file, if shared files are allowed or it's not shared
					if (    $ff->getType() === \OCP\Files\FileInfo::TYPE_FILE
						&& ($sharedAllowed || !$ff->isShared())
					) {
						$fileId = $ff->getId();
						$pictures_json_txt .= '"'. $this->toolsService->encodeURIComponent($row['path']) . '": ['.$row['lat'].', '.
							$row['lon'].', ' . $fileId . ', ' . ($row['date_taken'] ?? 0) . '],';
					}
				}
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		$pictures_json_txt = rtrim($pictures_json_txt, ',').'}';

		// delete absent files
		foreach ($dbToDelete as $path) {
			//error_log('I DELETE '.$path);
			$qb->delete('gpxpod_pictures')
				->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
				)
				->andWhere(
					$qb->expr()->eq('path', $qb->createNamedParameter($path, IQueryBuilder::PARAM_STR))
				);
			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}

		return $pictures_json_txt;
	}

	/**
	 * delete from DB all entries refering to absent files
	 * optionnal parameter : folder to clean
	 */
	private function cleanDbFromAbsentFiles($subfolder) {
		$qb = $this->dbconnection->getQueryBuilder();

		$subfo = $subfolder;
		if ($subfolder === '') {
			$subfo = '/';
		}
		$userFolder = $this->userfolder;
		$gpx_paths_to_del = [];

		$qb->select('trackpath')
			->from('gpxpod_tracks', 't')
			->where(
				$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
			);
		$req = $qb->execute();
		while ($row = $req->fetch()) {
			if (dirname($row['trackpath']) === $subfo || $subfo === null) {
				// delete DB entry if the file does not exist
				if (
					(! $userFolder->nodeExists($row['trackpath']))
					|| $userFolder->get($row['trackpath'])->getType() !== \OCP\Files\FileInfo::TYPE_FILE) {
					$gpx_paths_to_del[] = $row['trackpath'];
				}
			}
		}
		$req->closeCursor();
		$qb = $qb->resetQueryParts();

		if (count($gpx_paths_to_del) > 0) {
			$qb->delete('gpxpod_tracks')
				->where(
					$qb->expr()->eq('user', $qb->createNamedParameter($this->userId, IQueryBuilder::PARAM_STR))
				);

			$or = $qb->expr()->orx();
			foreach ($gpx_paths_to_del as $path_to_del) {
				$or->add($qb->expr()->eq('trackpath', $qb->createNamedParameter($path_to_del, IQueryBuilder::PARAM_STR)));
			}
			$qb->andWhere($or);

			$req = $qb->execute();
			$qb = $qb->resetQueryParts();
		}
	}

	/**
	 * method to get the URL to download a public file with OC/NC File system
	 * from the file object and the user who shares the file
	 *
	 * @return null if the file is not shared or inside a shared folder
	 */
	private function getPublinkDownloadURL($file, $username) {
		$uf = $this->root->getUserFolder($username);
		$dl_url = null;

		// CHECK if file is shared
		$shares = $this->shareManager->getSharesBy($username,
			\OCP\Share::SHARE_TYPE_LINK, $file, false, 1, 0);
		if (count($shares) > 0) {
			foreach($shares as $share) {
				if ($share->getPassword() === null) {
					$dl_url = $share->getToken();
					break;
				}
			}
		}

		if ($dl_url === null) {
			// CHECK if file is inside a shared folder
			$tmpfolder = $file->getParent();
			while ($tmpfolder->getPath() !== $uf->getPath() and
				$tmpfolder->getPath() !== "/" && $dl_url === null) {
				$shares_folder = $this->shareManager->getSharesBy($username,
					\OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
				if (count($shares_folder) > 0) {
					foreach($shares_folder as $share) {
						if ($share->getPassword() === null) {
							// one folder above the file is shared without passwd
							$token = $share->getToken();
							$subpath = str_replace($tmpfolder->getPath(), '', $file->getPath());
							$dl_url = $token.'/download?path=' . rtrim(dirname($subpath), '/');
							$dl_url .= '&files=' . $this->toolsService->encodeURIComponent(basename($subpath));

							break;
						}
					}
				}
				$tmpfolder = $tmpfolder->getParent();
			}
		}

		return $dl_url;
	}

	/**
	 * @return null if the file is not shared or inside a shared folder
	 */
	private function getPublinkParameters($file, $username) {
		$uf = $this->root->getUserFolder($username);
		$paramArray = null;

		// CHECK if file is shared
		$shares = $this->shareManager->getSharesBy($username,
			\OCP\Share::SHARE_TYPE_LINK, $file, false, 1, 0);
		if (count($shares) > 0) {
			foreach($shares as $share) {
				if ($share->getPassword() === null) {
					$paramArray = ['token' => $share->getToken(), 'path' => '', 'filename' => ''];
					break;
				}
			}
		}

		if ($paramArray === null) {
			// CHECK if file is inside a shared folder
			$tmpfolder = $file->getParent();
			while ($tmpfolder->getPath() !== $uf->getPath() and
				$tmpfolder->getPath() !== "/" && $paramArray === null) {
				$shares_folder = $this->shareManager->getSharesBy($username,
					\OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
				if (count($shares_folder) > 0) {
					foreach($shares_folder as $share) {
						if ($share->getPassword() === null) {
							// one folder above the file is shared without passwd
							$token = $share->getToken();
							$subpath = str_replace($tmpfolder->getPath(), '', $file->getPath());
							$filename = basename($subpath);
							$subpath = dirname($subpath);
							if ($subpath !== '/') {
								$subpath = rtrim($subpath, '/');
							}
							$paramArray = [
								'token' => $token,
								'path' => $subpath,
								'filename' => $filename
							];
							break;
						}
					}
				}
				$tmpfolder = $tmpfolder->getParent();
			}
		}

		return $paramArray;
	}

	/**
	 * Handle public link
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function publicFile() {
		if (!empty($_GET)) {
			$dbconnection = \OC::$server->getDatabaseConnection();
			$qb = $this->dbconnection->getQueryBuilder();
			$token = $_GET['token'];
			$path = '';
			$filename = '';
			if (isset($_GET['path'])) {
				$path = $_GET['path'];
			}
			if (isset($_GET['filename'])) {
				$filename = $_GET['filename'];
			}

			if ($path && $filename) {
				if ($path !== '/') {
					$dlpath = rtrim($path, '/');
				} else {
					$dlpath = $path;
				}
				$dl_url = $token.'/download?path=' . $this->toolsService->encodeURIComponent($dlpath);
				$dl_url .= '&files=' . $this->toolsService->encodeURIComponent($filename);
			} else {
				$dl_url = $token.'/download';
			}

			$share = $this->shareManager->getShareByToken($token);
			$user = $share->getSharedBy();
			$passwd = $share->getPassword();
			$shareNode = $share->getNode();
			$nodeid = $shareNode->getId();
			$uf = $this->root->getUserFolder($user);

			if ($passwd === null) {
				if ($path && $filename) {
					if ($shareNode->nodeExists($path . '/' . $filename)) {
						$theid = $shareNode->get($path . '/' . $filename)->getId();
						// we get the node for the user who shared
						// (the owner may be different if the file is shared from user to user)
						$thefile = $uf->getById($theid)[0];
					} else {
						return 'This file is not a public share';
					}
				} else {
					$thefile = $uf->getById($nodeid)[0];
				}

				if ($thefile->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
					$userfolder_path = $uf->getPath();
					$rel_file_path = str_replace($userfolder_path, '', $thefile->getPath());
					$rel_dir_path = dirname($rel_file_path);

					$markercontent = null;
					$qb->select('marker')
						->from('gpxpod_tracks', 't')
						->where(
							$qb->expr()->eq('user', $qb->createNamedParameter($user, IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->eq('trackpath', $qb->createNamedParameter($rel_file_path, IQueryBuilder::PARAM_STR))
						);
					$req = $qb->execute();

					while ($row = $req->fetch()) {
						$markercontent = $row['marker'];
						break;
					}
					$req->closeCursor();
					$qb = $qb->resetQueryParts();

					// file not found in DB => process
					if ($markercontent === null) {
						$optionValues = $this->getSharedMountedOptionValue($user);
						$sharedAllowed = $optionValues['sharedAllowed'];
						$mountedAllowed = $optionValues['mountedAllowed'];
						// process the whole directory
						$this->processGpxFiles($uf, $rel_dir_path, $user, false, $sharedAllowed, $mountedAllowed, false);

						$qb->select('marker')
							->from('gpxpod_tracks', 't')
							->where(
								$qb->expr()->eq('user', $qb->createNamedParameter($user, IQueryBuilder::PARAM_STR))
							)
							->andWhere(
								$qb->expr()->eq('trackpath', $qb->createNamedParameter($rel_file_path, IQueryBuilder::PARAM_STR))
							);
						$req = $qb->execute();

						while ($row = $req->fetch()) {
							$markercontent = $row['marker'];
							break;
						}
						$req->closeCursor();
						$qb = $qb->resetQueryParts();
					}

					$gpxContent = $this->toolsService->remove_utf8_bom($thefile->getContent());

				} else {
					return 'This file is not a public share';
				}
			} else {
				return 'This file is not a public share';
			}
		}

		$tss = $this->getUserTileServers('tile', $user, $_GET['layer'] ?? '');
		$mbtss = $this->getUserTileServers('mapboxtile', $user, $_GET['layer'] ?? '');
		$tssw = $this->getUserTileServers('tilewms', $user, $_GET['layer'] ?? '');
		$oss = $this->getUserTileServers('overlay', $user, $_GET['overlay'] ?? '');
		$ossw = $this->getUserTileServers('overlaywms', $user, $_GET['overlay'] ?? '');

		$extraSymbolList = $this->getExtraSymbolList();

		// PARAMS to send to template

		require_once('tileservers.php');
		$params = [
			'dirs' => [],
			'gpxcomp_root_url' => '',
			'username' => '',
			'hassrtm' => false,
			'basetileservers' => $baseTileServers,
			'usertileservers' => $tss,
			'usermapboxtileservers' => $mbtss,
			'useroverlayservers' => $oss,
			'usertileserverswms' => $tssw,
			'useroverlayserverswms' => $ossw,
			'publicgpx' => $gpxContent,
			'publicmarker' => $markercontent,
			'publicdir' => '',
			'pictures' => '',
			'token' => $dl_url,
			'extrasymbols' => $extraSymbolList,
			'gpxedit_version' => '',
			'gpxmotion_version' => '',
			'gpxpod_version' => $this->config->getAppValue('gpxpod', 'installed_version'),
		];
		$this->initialStateService->provideInitialState(
			'photos',
			$this->config->getAppValue('photos', 'enabled', 'no') === 'yes'
		);
		$response = new PublicTemplateResponse('gpxpod', 'main', $params);
		$response->setHeaderTitle($this->trans->t('GpxPod public access'));
		$response->setHeaderDetails($this->trans->t('Public file access'));
		$response->setFooterVisible(false);
		$response->setHeaders(['X-Frame-Options' => '']);
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

	private function getPubfolderDownloadURL($dir, $username) {
		$uf = $this->root->getUserFolder($username);
		$userfolder_path = $uf->getPath();
		$dl_url = null;

		// check that this is a directory
		if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {
			$shares_folder = $this->shareManager->getSharesBy($username,
				\OCP\Share::SHARE_TYPE_LINK, $dir, false, 1, 0);
			// check that this directory is publicly shared
			if (count($shares_folder) > 0) {
				foreach($shares_folder as $share) {
					if ($share->getPassword() === null) {
						// the directory is shared without passwd
						$token = $share->getToken();
						$dl_url = $token;
						//$dl_url = $token.'/download?path=';
						//$dl_url .= '&files=';
						break;
					}
				}
			}

			if ($dl_url === null) {
				// CHECK if folder is inside a shared folder
				$tmpfolder = $dir->getParent();
				while ($tmpfolder->getPath() !== $uf->getPath() and
					$tmpfolder->getPath() !== "/" && $dl_url === null) {
					$shares_folder = $this->shareManager->getSharesBy($username,
						\OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
					if (count($shares_folder) > 0) {
						foreach($shares_folder as $share) {
							if ($share->getPassword() === null) {
								// one folder above the dir is shared without passwd
								$token = $share->getToken();
								$subpath = str_replace($tmpfolder->getPath(), '', $dir->getPath());
								$dl_url = $token . '?path=' . rtrim($subpath, '/');

								break;
							}
						}
					}
					$tmpfolder = $tmpfolder->getParent();
				}
			}
		}

		return $dl_url;
	}

	private function getPubfolderParameters($dir, $username) {
		$uf = $this->root->getUserFolder($username);
		$userfolder_path = $uf->getPath();
		$paramArray = null;

		// check that this is a directory
		if ($dir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {
			$shares_folder = $this->shareManager->getSharesBy($username,
				\OCP\Share::SHARE_TYPE_LINK, $dir, false, 1, 0);
			// check that this directory is publicly shared
			if (count($shares_folder) > 0) {
				foreach($shares_folder as $share) {
					if ($share->getPassword() === null) {
						// the directory is shared without passwd
						$paramArray = ['token' => $share->getToken(), 'path' => ''];
						break;
					}
				}
			}

			if ($paramArray === null) {
				// CHECK if folder is inside a shared folder
				$tmpfolder = $dir->getParent();
				while ($tmpfolder->getPath() !== $uf->getPath() and
					$tmpfolder->getPath() !== "/" && $paramArray === null) {
					$shares_folder = $this->shareManager->getSharesBy($username,
						\OCP\Share::SHARE_TYPE_LINK, $tmpfolder, false, 1, 0);
					if (count($shares_folder) > 0) {
						foreach($shares_folder as $share) {
							if ($share->getPassword() === null) {
								// one folder above the dir is shared without passwd
								$token = $share->getToken();
								$subpath = str_replace($tmpfolder->getPath(), '', $dir->getPath());
								if ($subpath !== '/') {
									$subpath = rtrim($subpath, '/');
								}
								$paramArray = ['token' => $share->getToken(), 'path' => $subpath];
								break;
							}
						}
					}
					$tmpfolder = $tmpfolder->getParent();
				}
			}
		}

		return $paramArray;
	}

	/**
	 * Handle public directory link view request from share
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function publicFolder() {
		if (!empty($_GET)) {
			$dbconnection = \OC::$server->getDatabaseConnection();
			$qb = $this->dbconnection->getQueryBuilder();
			$token = $_GET['token'];
			$path = '';
			if (isset($_GET['path'])) {
				$path = $_GET['path'];
			}

			if ($path) {
				$dl_url = $token.'?path='.$this->toolsService->encodeURIComponent($path);
			} else {
				$dl_url = $token.'?path=/';
			}

			$share = $this->shareManager->getShareByToken($token);
			$user = $share->getSharedBy();
			$passwd = $share->getPassword();
			$shareNode = $share->getNode();
			$nodeid = $shareNode->getId();
			$target = $share->getTarget();
			$uf = $this->root->getUserFolder($user);

			if ($passwd === null) {
				if ($path) {
					if ($shareNode->nodeExists($path)) {
						$theid = $shareNode->get($path)->getId();
						// we get the node for the user who shared
						// (the owner may be different if the file is shared from user to user)
						$thedir = $uf->getById($theid)[0];
					} else {
						return "This directory is not a public share";
					}
				} else {
					$thedir = $uf->getById($nodeid)[0];
				}

				if ($thedir->getType() === \OCP\Files\FileInfo::TYPE_FOLDER) {
					$userfolder_path = $uf->getPath();

					$rel_dir_path = str_replace($userfolder_path, '', $thedir->getPath());
					$rel_dir_path = rtrim($rel_dir_path, '/');

					$optionValues = $this->getSharedMountedOptionValue($user);
					$sharedAllowed = $optionValues['sharedAllowed'];
					$mountedAllowed = $optionValues['mountedAllowed'];

					$filesByExtension = [];
					foreach($this->extensions as $ext => $gpsbabel_fmt) {
						$filesByExtension[$ext] = [];
					}

					// get files (not recursively)
					foreach ($uf->get($rel_dir_path)->getDirectoryListing() as $ff) {
						if ($ff->getType() === \OCP\Files\FileInfo::TYPE_FILE) {
							$ffext = '.'.strtolower(pathinfo($ff->getName(), PATHINFO_EXTENSION));
							if (in_array( $ffext, array_keys($this->extensions))) {
								// if shared files are allowed or it is not shared
								if ($sharedAllowed || !$ff->isShared()) {
									$filesByExtension[$ffext][] = $ff;
								}
							}
						}
					}
					// generate metadata
					$this->convertFiles($uf, $rel_dir_path, $user, $filesByExtension);
					$this->processGpxFiles($uf, $rel_dir_path, $user, false, $sharedAllowed, $mountedAllowed, false);

					// get the tracks data from DB
					$qb->select('id', 'trackpath', 'marker')
						->from('gpxpod_tracks', 't')
						->where(
							$qb->expr()->eq('user', $qb->createNamedParameter($user, IQueryBuilder::PARAM_STR))
						)
						->andWhere(
							$qb->expr()->like('trackpath', $qb->createNamedParameter($rel_dir_path.'%', IQueryBuilder::PARAM_STR))
						);
					$req = $qb->execute();

					$markertxt = '{"markers" : {';
					while ($row = $req->fetch()) {
						if (dirname($row['trackpath']) === $rel_dir_path) {
							$trackname = basename($row['trackpath']);
							$markertxt .= '"'.$row['id'].'": '.$row['marker'];
							$markertxt .= ',';
						}
					}
					$req->closeCursor();
					$qb = $qb->resetQueryParts();

					$markertxt = rtrim($markertxt, ',');
					$markertxt .= '}}';
				} else {
					return "This directory is not a public share";
				}
			} else {
				return "This directory is not a public share";
			}
			$pictures_json_txt = $this->getGeoPicsFromFolder($rel_dir_path, false, $user);
		}

		$tss = $this->getUserTileServers('tile', $user, $_GET['layer'] ?? '');
		$mbtss = $this->getUserTileServers('mapboxtile', $user, $_GET['layer'] ?? '');
		$tssw = $this->getUserTileServers('tilewms', $user, $_GET['layer'] ?? '');
		$oss = $this->getUserTileServers('overlay', $user, $_GET['overlay'] ?? '');
		$ossw = $this->getUserTileServers('overlaywms', $user, $_GET['overlay'] ?? '');

		$extraSymbolList = $this->getExtraSymbolList();

		// PARAMS to send to template

		require_once('tileservers.php');
		$params = [
			'dirs' => [],
			'gpxcomp_root_url' => '',
			'username' => $user,
			'hassrtm' => false,
			'basetileservers' => $baseTileServers,
			'usertileservers' => $tss,
			'usermapboxtileservers' => $mbtss,
			'useroverlayservers' => $oss,
			'usertileserverswms' => $tssw,
			'useroverlayserverswms' => $ossw,
			'publicgpx' => '',
			'publicmarker' => $markertxt,
			'publicdir' => $rel_dir_path,
			'token' => $dl_url,
			'pictures' => $pictures_json_txt,
			'extrasymbols' => $extraSymbolList,
			'gpxedit_version' => '',
			'gpxmotion_version' => '',
			'gpxpod_version' => $this->config->getAppValue('gpxpod', 'installed_version'),
		];
		$this->initialStateService->provideInitialState(
			'photos',
			$this->config->getAppValue('photos', 'enabled', 'no') === 'yes'
		);
		$response = new PublicTemplateResponse('gpxpod', 'main', $params);
		$response->setHeaderTitle($this->trans->t('GpxPod public access'));
		$response->setHeaderDetails($this->trans->t('Public folder access'));
		$response->setFooterVisible(false);
		$response->setHeaders(['X-Frame-Options' => '']);
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
	 */
	public function isFileShareable($trackpath) {
		$uf = $this->root->getUserFolder($this->userId);
		$isIt = false;

		if ($uf->nodeExists($trackpath)) {
			$thefile = $uf->get($trackpath);
			$publinkParameters = $this->getPublinkParameters($thefile, $this->userId);
			if ($publinkParameters !== null) {
				$isIt = true;
			} else {
				$publinkParameters = ['token' => '','path' => '','filename' => ''];
			}
		}

		$response = new DataResponse(
			[
				'response' => $isIt,
				'token' => $publinkParameters['token'],
				'path' => $publinkParameters['path'],
				'filename' => $publinkParameters['filename']
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
	 */
	public function isFolderShareable($folderpath) {
		$uf = $this->root->getUserFolder($this->userId);
		$isIt = false;

		if ($uf->nodeExists($folderpath)) {
			$thefolder = $uf->get($folderpath);
			$pubFolderParams = $this->getPubfolderParameters($thefolder, $this->userId);
			if ($pubFolderParams !== null) {
				$isIt = true;
			} else {
				$pubFolderParams = ['token' => '','path' => ''];
			}
		}

		$response = new DataResponse(
			[
				'response' => $isIt,
				'token' => $pubFolderParams['token'],
				'path' => $pubFolderParams['path']
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
	 */
	public function deleteTracks($paths) {
		$uf = $this->root->getUserFolder($this->userId);
		$done = False;
		$deleted = '';
		$notdeleted = '';
		$message = '';

		foreach ($paths as $path) {
			$cleanPath = str_replace(['../', '..\\'], '', $path);
			if ($uf->nodeExists($cleanPath)) {
				$file = $uf->get($cleanPath);
				if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE
					&& $file->isDeletable()) {
					$file->delete();
					$deleted .= $cleanPath.', ';
				} else {
					$notdeleted .= $cleanPath.', ';
				}
			} else {
				$notdeleted .= $cleanPath.', ';
			}
		}
		$done = True;

		$deleted = rtrim($deleted, ', ');
		$notdeleted = rtrim($notdeleted, ', ');

		$response = new DataResponse([
			'message' => $message,
			'deleted' => $deleted,
			'notdeleted' => $notdeleted,
			'done' => $done
		]);
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedImageDomain('*')
			->addAllowedMediaDomain('*')
			->addAllowedConnectDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}
}
