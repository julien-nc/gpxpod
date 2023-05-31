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

use Exception;
use OC\User\NoUserException;
use OCA\GpxPod\Db\TileServer;
use OCA\GpxPod\Db\TileServerMapper;
use OCA\GpxPod\Service\KmlConversionService;
use OCA\GpxPod\Service\MapService;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\Files\File;
use OCA\GpxPod\AppInfo\Application;

use OCA\GpxPod\Db\Directory;
use OCA\GpxPod\Db\DirectoryMapper;
use OCA\GpxPod\Db\TrackMapper;
use OCA\GpxPod\Service\ConversionService;
use OCA\GpxPod\Service\SrtmGeotiffElevationService;
use OCA\GpxPod\Service\ProcessService;
use OCA\GpxPod\Service\ToolsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use phpGPX\Models\GpxFile;
use phpGPX\Models\Point;
use phpGPX\Models\Route;
use phpGPX\Models\Segment;
use phpGPX\Models\Track;
use phpGPX\phpGPX;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use Psr\Log\LoggerInterface;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

class PageController extends Controller {

	private array $upperExtensions;

	public function __construct(
		$appName,
		IRequest $request,
		private LoggerInterface $logger,
		private IConfig $config,
		private IInitialState $initialStateService,
		private IRootFolder $root,
		private ProcessService $processService,
		private ConversionService $conversionService,
		private ToolsService $toolsService,
		private SrtmGeotiffElevationService $elevationService,
		private MapService $mapService,
		private DirectoryMapper $directoryMapper,
		private TrackMapper $trackMapper,
		private TileServerMapper $tileServerMapper,
		private IManager $shareManager,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private KmlConversionService $kmlConversionService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
		$this->upperExtensions = array_map('strtoupper', array_keys(ConversionService::fileExtToGpsbabelFormat));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $service
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @return DataDisplayResponse
	 * @throws Exception
	 */
	public function getRasterTile(string $service, int $x, int $y, int $z): DataDisplayResponse {
		try {
			$response = new DataDisplayResponse($this->mapService->getRasterTile($service, $x, $y, $z));
			$response->cacheFor(60 * 60 * 24);
			return $response;
		} catch (Exception | Throwable $e) {
			return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $query
	 * @return DataResponse
	 */
	public function nominatimSearch(string $query): DataResponse {
		$searchResults = $this->mapService->searchLocation($this->userId, $query, 0, 10);
		if (isset($searchResults['error'])) {
			return new DataResponse('', Http::STATUS_BAD_REQUEST);
		}
		$response = new DataResponse($searchResults);
		$response->cacheFor(60 * 60 * 24, false, true);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 * @throws \OCP\DB\Exception
	 */
	public function index(): TemplateResponse {
		$this->cleanDbFromAbsentFiles($this->userId, null);
		$alldirs = $this->getDirectories($this->userId);

		// personal settings
		$settings = [];
		$keys = $this->config->getUserKeys($this->userId, Application::APP_ID);
		foreach ($keys as $key) {
			$value = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			$settings[$key] = $value;
		}

		$adminMaptilerApiKey = $this->config->getAppValue(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$maptilerApiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'maptiler_api_key', $adminMaptilerApiKey) ?: $adminMaptilerApiKey;
		$settings['maptiler_api_key'] = $maptilerApiKey;

		$settings = $this->getDefaultSettings($settings);

		$dirObj = [];
		foreach ($alldirs as $dir) {
			$dirObj[$dir['id']] = [
				'id' => $dir['id'],
				'path' => $dir['path'],
				'isOpen' => $dir['isOpen'],
				'sortOrder' => $dir['sortOrder'],
				'sortAsc' => $dir['sortAsc'],
				'recursive' => $dir['recursive'],
				'tracks' => [],
				'pictures' => [],
				'loading' => false,
			];
		}

		$userTileServers = $this->tileServerMapper->getTileServersOfUser($this->userId);
		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);
		$extraTileServers = array_merge($userTileServers, $adminTileServers);
		$settings['extra_tile_servers'] = $extraTileServers;

		$state = [
			'directories' => $dirObj,
			'settings' => $settings,
		];
		$this->initialStateService->provideInitialState(
			'gpxpod-state',
			$state
		);

		$response = new TemplateResponse(Application::APP_ID, 'newMain');
		$csp = new ContentSecurityPolicy();
		$this->mapService->addPageCsp($csp, $extraTileServers);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $shareToken
	 * @param string $password
	 * @param string|null $embedded
	 * @return Response
	 * @throws NotFoundException
	 * @throws \OCP\DB\Exception
	 */
	public function publicPasswordIndex(string $shareToken, string $password, ?string $embedded = null): Response {
		return $this->publicIndex($shareToken, $password, null, $embedded);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @BruteForceProtection(action=gpxpodPublicIndex)
	 *
	 * @param string $shareToken
	 * @param string|null $password
	 * @param string|null $path
	 * @param string|null $embedded
	 * @return Response
	 * @throws DoesNotExistException
	 * @throws LockedException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function publicIndex(string $shareToken, ?string $password = null, ?string $path = null, ?string $embedded = null): Response {
		// check if share exists
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
		} catch (ShareNotFound $e) {
			$response = new TemplateResponse(
				'',
				'error',
				[
					'errors' => [
						['error' => $this->l10n->t('Share not found')],
					],
				],
				TemplateResponse::RENDER_AS_ERROR
			);
			$response->setStatus(Http::STATUS_NOT_FOUND);
			$response->throttle(['share_not_found' => $shareToken]);
			return $response;
		}
		// check if share is password protected
		$sharePassword = $share->getPassword();
		if ($sharePassword
			&& (
				$password === null
				|| !$this->shareManager->checkPassword($share, $password)
			)
		) {
			// if so: return password form template response
			$embedSuffix = $embedded === '1' ? '?embedded=1' : '';
			$params = [
				'action' => $this->urlGenerator->linkToRouteAbsolute('gpxpod.page.publicIndex', ['shareToken' => $shareToken]) . $embedSuffix,
			];
			// if a password was given, it is incorrect
			if ($password !== null) {
				$params['wrong'] = true;
			}
			// PARAMS to view
			$response = new PublicTemplateResponse(Application::APP_ID, 'sharePassword', $params);
			$response->setHeaderTitle($this->l10n->t('GpxPod public access'));
//			$response->setHeaderDetails($this->l10n->t('Enter shared access password'));
			$response->setFooterVisible(false);
			$csp = new ContentSecurityPolicy();
			$csp->addAllowedFrameAncestorDomain('*');
			$response->setContentSecurityPolicy($csp);
			if (!$this->shareManager->checkPassword($share, $password)) {
				$response->throttle(['invalid_share_password' => $shareToken]);
			}
			return $response;
		}

		// if not: return real public index with initial state
		return $this->getPublicTemplate($share, $password, $path, $embedded === '1');
	}

	/**
	 * @param IShare $share
	 * @param string|null $password
	 * @param string|null $path
	 * @param bool $embeded
	 * @return TemplateResponse
	 * @throws DoesNotExistException
	 * @throws LockedException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	private function getPublicTemplate(IShare $share, ?string $password, ?string $path, bool $embeded = false): TemplateResponse {
		$shareOwner = $share->getShareOwner();
		$adminMaptilerApiKey = $this->config->getAppValue(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$maptilerApiKey = $this->config->getUserValue($shareOwner, Application::APP_ID, 'maptiler_api_key', $adminMaptilerApiKey) ?: $adminMaptilerApiKey;
		$settings = [
			'show_mouse_position_control' => '1',
			'global_track_colorization' => '0',
			'show_marker_cluster' => '0',
			'maptiler_api_key' => $maptilerApiKey,
		];
		$settings = $this->getDefaultSettings($settings);

		$userTileServers = $this->tileServerMapper->getTileServersOfUser($shareOwner);
		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);
		$extraTileServers = array_merge($userTileServers, $adminTileServers);
		$settings['extra_tile_servers'] = $extraTileServers;

		$state = [
			'shareToken' => $share->getToken(),
			'directories' => [],
			'settings' => $settings,
		];
		if ($password !== null) {
			$state['sharePassword'] = $password;
		}

		$shareNode = $share->getNode();
		if ($shareNode instanceof File) {
			$state['shareTargetType'] = 'file';
			$state['directories'] = [
				$share->getToken() => [
					'id' => $share->getToken(),
					'path' => $this->l10n->t('Public link'),
					'isOpen' => true,
					'sortOrder' => 0,
					'sortAsc' => true,
					'recursive' => false,
					'tracks' => [
						'0' => $this->getPublicTrack($share, $shareNode),
					],
					'pictures' => [],
					'loading' => false,
				],
			];
			$targetNode = $shareNode;
		} elseif ($shareNode instanceof Folder) {
			if ($path === null) {
				$state['shareTargetType'] = 'folder';
				$state['directories'] = [
					$share->getToken() => [
						'id' => $share->getToken(),
						'path' => $shareNode->getName(),
						'isOpen' => true,
						'sortOrder' => 0,
						'sortAsc' => true,
						'recursive' => false,
						'tracks' => $this->getPublicDirectoryTracks($share, $shareNode),
						'pictures' => [],
						'loading' => false,
					],
				];
				$targetNode = $shareNode;
			} else {
				if ($shareNode->nodeExists($path)) {
					$targetNode = $shareNode->get($path);
					if ($targetNode instanceof File) {
						$state['shareTargetType'] = 'file';
						$state['directories'] = [
							$share->getToken() => [
								'id' => $share->getToken(),
								'path' => $this->l10n->t('Public link'),
								'isOpen' => true,
								'sortOrder' => 0,
								'sortAsc' => true,
								'recursive' => false,
								'tracks' => [
									'0' => $this->getPublicTrack($share, $targetNode),
								],
								'pictures' => [],
								'loading' => false,
							],
						];
					} elseif ($targetNode instanceof Folder) {
						$state['shareTargetType'] = 'folder';
						$state['directories'] = [
							$share->getToken() => [
								'id' => $share->getToken(),
								'path' => $shareNode->getName() . '/' . ltrim($path, '/'),
								'isOpen' => true,
								'sortOrder' => 0,
								'sortAsc' => true,
								'recursive' => false,
								'tracks' => $this->getPublicDirectoryTracks($share, $targetNode),
								'pictures' => [],
								'loading' => false,
							],
						];
					}
				} else {
					$response = new TemplateResponse(
						'',
						'error',
						[
							'errors' => [
								['error' => $this->l10n->t('Path not found in share')],
							],
						],
						TemplateResponse::RENDER_AS_ERROR
					);
					$response->setStatus(Http::STATUS_NOT_FOUND);
					$response->throttle(['path_not_found' => $path, 'share_token' => $share->getToken()]);
					return $response;
				}
			}
		}

		$this->initialStateService->provideInitialState('gpxpod-state', $state);

		if ($embeded) {
			$response = new TemplateResponse(Application::APP_ID, 'newMain', [], TemplateResponse::RENDER_AS_BASE);
		} else {
			$response = new PublicTemplateResponse(Application::APP_ID, 'newMain');
			$response->setHeaderTitle($targetNode->getName());
			$response->setHeaderDetails(
				$targetNode instanceof File
					? $this->l10n->t('GpxPod public file share')
					: $this->l10n->t('GpxPod public directory share')
			);
			$response->setFooterVisible(false);
		}
		$csp = new ContentSecurityPolicy();
		$this->mapService->addPageCsp($csp, $extraTileServers);
		$csp->addAllowedFrameAncestorDomain('*');
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param IShare $share
	 * @param Folder $sharedDir
	 * @return array
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	private function getPublicDirectoryTracks(IShare $share, Folder $sharedDir): array {
		$sharedBy = $share->getSharedBy();
		$directoryPath = preg_replace('/^files/', '', $sharedDir->getInternalPath());
//		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUserByPath($directoryPath, $sharedBy);
//		} catch (\OCP\DB\Exception | DoesNotExistException $e) {
//			TODO handle this error
//		}
		$this->processService->processGpxFiles($sharedBy, $dbDir->getId(), true, true, false);

		$dbTracks = $this->trackMapper->getDirectoryTracksOfUser($sharedBy, $dbDir->getId());

		$jsonTracks = array_map(static function(\OCA\GpxPod\Db\Track $track) use ($share) {
			$jsonTrack = $track->jsonSerialize();
			$jsonTrack['geojson'] = null;
			$jsonTrack['onTop'] = false;
			$jsonTrack['loading'] = false;
			$jsonTrack['directoryId'] = $share->getToken();
			$jsonTrack['trackpath'] = basename($jsonTrack['trackpath']);
			$jsonTrack['color'] = $jsonTrack['color'] ?? '#0693e3';
			$decodedMarker = json_decode($jsonTrack['marker'], true);
			foreach (Application::MARKER_FIELDS as $k => $v) {
				$jsonTrack[$k] = $decodedMarker[$k];
			}
			unset($jsonTrack['marker']);
			return $jsonTrack;
		}, $dbTracks);

		$tracksById = [];
		foreach ($jsonTracks as $jsonTrack) {
			$tracksById[$jsonTrack['id']] = $jsonTrack;
		}

		return $tracksById;
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @NoCSRFRequired
	 * @BruteForceProtection(action=gpxpodPublicIndex)
	 *
	 * @param string $shareToken
	 * @param int $trackId
	 * @param string|null $password
	 * @return DataResponse
	 * @throws LockedException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function getPublicDirectoryTrackGeojson(string $shareToken, int $trackId, ?string $password = null): DataResponse {
		// check if share exists
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
		} catch (ShareNotFound $e) {
			$response = new DataResponse('', Http::STATUS_NOT_FOUND);
			$response->throttle(['share_not_found' => $shareToken]);
			return $response;
		}
		// check share password
		$sharePassword = $share->getPassword();
		if ($sharePassword && !$this->shareManager->checkPassword($share, $password)) {
			$response = new DataResponse('p', Http::STATUS_NOT_FOUND);
			$response->throttle(['invalid_share_password' => $shareToken]);
			return $response;
		}

		$sharedBy = $share->getSharedBy();

		try {
			$dbTrack = $this->trackMapper->getTrackOfUser($trackId, $sharedBy);
		} catch (DoesNotExistException $e) {
			return new DataResponse('t', Http::STATUS_NOT_FOUND);
		}

		$dirId = $dbTrack->getDirectoryId();

		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUser($dirId, $sharedBy);
		} catch (DoesNotExistException $e) {
			return new DataResponse('d', Http::STATUS_NOT_FOUND);
		}

		$sharedDirPath = preg_replace('/^files/', '', $share->getNode()->getInternalPath());
		// 2 ways it's correct:
		// - the dir pointed by the share is the track dir
		// - the dir pointed by the share is a parent of the track dir
		if ($dbDir->getPath() !== $sharedDirPath && !str_starts_with($dbDir->getPath(), $sharedDirPath)) {
			return new DataResponse('s', Http::STATUS_NOT_FOUND);
		}

		$userFolder = $this->root->getUserFolder($sharedBy);
		$cleanPath = str_replace(['../', '..\\'], '',  $dbTrack->getTrackpath());
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File) {
				if (preg_match('/\.gpx$/i', $file->getName()) === 1) {
					$geojsonArray = $this->gpxToGeojson($file->getContent());
					return new DataResponse($geojsonArray);
				}
			}
		}

		return new DataResponse('e', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @param IShare $share
	 * @param File $trackFile
	 * @return array
	 * @throws DoesNotExistException
	 * @throws LockedException
	 * @throws MultipleObjectsReturnedException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	private function getPublicTrack(IShare $share, File $trackFile): array {
		$sharedBy = $share->getSharedBy();
		$trackPath = preg_replace('/^files/', '', $trackFile->getInternalPath());
//		try {
			$track = $this->trackMapper->getTrackOfUserByPath($sharedBy, $trackPath);
//		} catch (DoesNotExistException $e) {
//			 TODO process the parent directory (problem, we now pass dirId to processService->processGpxFiles())
//		}
		$jsonTrack = $track->jsonSerialize();
		$jsonTrack['id'] = 0;
		$jsonTrack['isEnabled'] = true;

		$geojsonArray = $this->gpxToGeojson($trackFile->getContent());
		$jsonTrack['geojson'] = $geojsonArray;

		$jsonTrack['onTop'] = false;
		$jsonTrack['loading'] = false;
		$jsonTrack['colorExtensionCriteria'] = '';
		$jsonTrack['colorExtensionCriteriaType'] = '';
		$jsonTrack['directoryId'] = $share->getToken();
		$jsonTrack['trackpath'] = basename($jsonTrack['trackpath']);
		$jsonTrack['color'] = $jsonTrack['color'] ?? '#0693e3';
		$decodedMarker = json_decode($jsonTrack['marker'], true);
		foreach (Application::MARKER_FIELDS as $k => $v) {
			$jsonTrack[$k] = $decodedMarker[$k];
		}
		unset($jsonTrack['marker']);
		return $jsonTrack;
	}

	/**
	 * @param array $settings
	 * @return array
	 */
	private function getDefaultSettings(array $settings): array {
		$settings['app_version'] = $this->config->getAppValue(Application::APP_ID, 'installed_version');
		// for vue reactive props, initialize missing ones that have an immediate effect on the map
		if (!isset($settings['chart_hover_show_detailed_popup'])) {
			$settings['chart_hover_show_detailed_popup'] = '0';
		}
		if (!isset($settings['follow_chart_hover'])) {
			$settings['follow_chart_hover'] = '1';
		}
		if (!isset($settings['global_track_colorization'])) {
			$settings['global_track_colorization'] = '0';
		}
		if (!isset($settings['show_marker_cluster'])) {
			$settings['show_marker_cluster'] = '1';
		}
		if (!isset($settings['show_picture_cluster'])) {
			$settings['show_picture_cluster'] = '1';
		}
		if (!isset($settings['chart_x_axis'])) {
			$settings['chart_x_axis'] = 'time';
		}
		if (!isset($settings['nav_tracks_filter_map_bounds'])) {
			$settings['nav_tracks_filter_map_bounds'] = '';
		}
		if (!isset($settings['nav_show_hovered_dir_bounds'])) {
			$settings['nav_show_hovered_dir_bounds'] = '';
		}
		if (!isset($settings['show_mouse_position_control'])) {
			$settings['show_mouse_position_control'] = '';
		}
		if (!isset($settings['use_terrain'])) {
			$settings['use_terrain'] = '';
		}
		if (!isset($settings['compact_mode'])) {
			$settings['compact_mode'] = '1';
		}
		if (!isset($settings['selected_directory_id'])) {
			$settings['selected_directory_id'] = '';
		}
		if (!isset($settings['mapStyle'])) {
			$settings['mapStyle'] = 'osmRaster';
		}
		if (!isset($settings['terrainExaggeration'])) {
			$settings['terrainExaggeration'] = 2.5;
		} else {
			$settings['terrainExaggeration'] = (float) $settings['terrainExaggeration'];
		}
		return $settings;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param bool $isOpen
	 * @param int|null $sortOrder
	 * @param bool|null $sortAsc
	 * @param bool|null $recursive
	 * @return DataResponse
	 * @throws \OCP\DB\Exception
	 */
	public function updateDirectory(
		int $id, ?bool $isOpen = null, ?int $sortOrder = null,
		?bool $sortAsc = null, ?bool $recursive = null
	): DataResponse {
		$this->directoryMapper->updateDirectory($id, $this->userId, null, $isOpen, $sortOrder, $sortAsc, $recursive);
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param bool|null $isEnabled
	 * @param string|null $color
	 * @param int|null $colorCriteria
	 * @return DataResponse
	 * @throws \OCP\DB\Exception
	 */
	public function updateTrack(int $id, ?bool $isEnabled = null, ?string $color = null, ?int $colorCriteria = null): DataResponse {
		$this->trackMapper->updateTrack($id, $this->userId, null, null, $isEnabled, $color, $colorCriteria);
		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * no CSRF because this can be called from the files app
	 *
	 * @param string $path
	 * @param bool $recursive
	 * @return DataResponse
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function addDirectory(string $path, bool $recursive = false): DataResponse {
		if ($recursive) {
			return $this->addDirectoryRecursive($path);
		}
		$userFolder = $this->root->getUserFolder($this->userId);

		$cleanPath = str_replace(['../', '..\\'], '', $path);
		if ($userFolder->nodeExists($cleanPath)) {
			try {
				$dir = $this->directoryMapper->createDirectory($cleanPath, $this->userId, false);
				$addedId = $dir->getId();
			} catch (\OCP\DB\Exception $e) {
				return new DataResponse('Impossible to insert. ' . $e->getMessage(), 400);
			}
			return new DataResponse($addedId);
		} else {
			return new DataResponse($cleanPath . ' does not exist', 400);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $path
	 * @return DataResponse
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function addDirectoryRecursive(string $path): DataResponse {
		$userFolder = $this->root->getUserFolder($this->userId);
		$userFolderPath = $userFolder->getPath();

		$cleanPath = str_replace(['../', '..\\'], '',  $path);
		if ($userFolder->nodeExists($cleanPath)) {
			$folder = $userFolder->get($cleanPath);

			// DIRS array population
			$optionValues = $this->processService->getSharedMountedOptionValue($this->userId);
			$sharedAllowed = $optionValues['sharedAllowed'];
			$mountedAllowed = $optionValues['mountedAllowed'];
			$showpicsonlyfold = $this->config->getUserValue($this->userId, 'gpxpod', 'showpicsonlyfold', 'true');
			$searchJpg = ($showpicsonlyfold === 'true');
			$extensions = array_keys(ConversionService::fileExtToGpsbabelFormat);
			if ($searchJpg) {
				$extensions = array_merge($extensions, ['.jpg']);
			}
			$files = $this->processService->searchFilesWithExt($folder, $sharedAllowed, $mountedAllowed, $extensions);
			$alldirs = [];
			foreach($files as $file) {
				if ($file->getType() === FileInfo::TYPE_FILE and
					// name extension is supported
					(
						in_array('.'.pathinfo($file->getName(), PATHINFO_EXTENSION), array_keys(ConversionService::fileExtToGpsbabelFormat))
						|| in_array('.'.pathinfo($file->getName(), PATHINFO_EXTENSION), $this->upperExtensions)
					)
				) {
					$rel_dir = str_replace($userFolderPath, '', dirname($file->getPath()));
					$rel_dir = str_replace('//', '/', $rel_dir);
					if ($rel_dir === '') {
						$rel_dir = '/';
					}
					if (!in_array($rel_dir, $alldirs)) {
						$alldirs[] = $rel_dir;
					}
				}
			}

			// add each directory
			$addedDirs = [];
			foreach ($alldirs as $path) {
				try {
					$insertedDir = $this->directoryMapper->createDirectory($path, $this->userId, false);
					$addedDirs[] = $insertedDir->jsonSerialize();
				} catch (\OCP\DB\Exception $e) {
					// ignore this dir
				}
			}
			return new DataResponse($addedDirs);
		} else {
			return new DataResponse($cleanPath . ' does not exist', 400);
		}
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @return DataResponse
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function deleteDirectory(int $id): DataResponse {
		try {
			$dir = $this->directoryMapper->getDirectoryOfUser($id, $this->userId);
		} catch (DoesNotExistException $e) {
			return new DataResponse('Directory not found', Http::STATUS_BAD_REQUEST);
		}
		$this->directoryMapper->delete($dir);
		$this->trackMapper->deleteDirectoryTracksForUser($this->userId, $id);
		return new DataResponse('');
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $userId
	 * @return array
	 * @throws \OCP\DB\Exception
	 */
	public function getDirectories(string $userId): array {
		return array_map(static function(Directory $directory) {
			return $directory->jsonSerialize();
		}, $this->directoryMapper->getDirectoriesOfUser($userId));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @return DataResponse
	 * @throws LockedException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function getGeojson(int $id): DataResponse {
		try {
			$dbTrack = $this->trackMapper->getTrackOfUser($id, $this->userId);
		} catch (DoesNotExistException $e) {
			return new DataResponse('Track not found', Http::STATUS_BAD_REQUEST);
		}

		$path = $dbTrack->getTrackpath();
		$userFolder = $this->root->getUserFolder($this->userId);

		$cleanPath = str_replace(['../', '..\\'], '',  $path);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File) {
				if ($this->toolsService->endswith($file->getName(), '.GPX') || $this->toolsService->endswith($file->getName(), '.gpx')) {
					$geojsonArray = $this->gpxToGeojson($file->getContent());
					return new DataResponse($geojsonArray);
				}
			}
		}

		return new DataResponse('', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @param string $gpxContent
	 * @return array
	 */
	private function gpxToGeojson(string $gpxContent): array {
		$gpxContent = $this->toolsService->remove_utf8_bom($gpxContent);
		$gpxContent = $this->toolsService->sanitizeGpxContent($gpxContent);
		try {
			$gpxContent = $this->conversionService->sanitizeGpxExtensions($gpxContent);
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Error in sanitizeGpxExtensions', ['app' => Application::APP_ID, 'exception' => $e]);
		}
		$gpx = new phpGPX();
		$gpxArray = $gpx->parse($gpxContent);

		return [
			'type' => 'FeatureCollection',
			'features' => $this->getGeojsonFeatures($gpxArray),
		];
	}

	public function getGeojsonFeatures(GpxFile $gpxArray): array {
		// one multiline per gpx-track
		// one series of coords per gpx-segment
		$trackFeatures = array_map(function(Track $track) {
			return [
				'type' => 'Feature',
				'geometry' => [
					'type' => 'MultiLineString',
					'coordinates' => array_map(function(Segment $segment) {
						return array_map(function(Point $point) {
							return $this->getGeojsonPoint($point);
						}, array_values(array_filter($segment->points, static function(Point $point) {
							// && $point->time !== null;
							return $point->longitude !== null && $point->latitude !== null;
						})));
					}, $track->segments)
				],
				'properties' => [
					'name' => $track->name,
					'comment' => $track->comment,
					'description' => $track->description,
					// TODO show track extensions in the UI
					'extensions' => $track->extensions !== null ? $track->extensions->toArray() : null,
				],
			];
		}, $gpxArray->tracks);

		// one line per route
		$routeFeatures = array_map(function(Route $route) {
			return [
				'type' => 'Feature',
				'geometry' => [
					'type' => 'LineString',
					'coordinates' => array_map(function (Point $point) {
						return $this->getGeojsonPoint($point);
					}, array_values(array_filter($route->points, static function(Point $point) {
						// && $point->time !== null;
						return $point->longitude !== null && $point->latitude !== null;
					})))
				],
				'properties' => [
					'name' => $route->name,
					'comment' => $route->comment,
					'description' => $route->description,
					// TODO show route extensions in the UI
					'extensions' => $route->extensions !== null ? $route->extensions->toArray() : null,
				],
			];
		}, $gpxArray->routes);

		// one point per waypoint
		$waypointFeatures = array_map(function(Point $waypoint) {
			$symbolInfo = $this->getSymbolInfo($waypoint);
			return [
				'type' => 'Feature',
				'geometry' => [
					'type' => 'Point',
					'coordinates' => $this->getGeojsonPoint($waypoint),
				],
				'properties' => [
					'name' => $waypoint->name,
					'elevation' => $waypoint->elevation,
					'time' => $waypoint->time !== null ? $waypoint->time->getTimestamp() : null,
					'lng' => $waypoint->longitude,
					'lat' => $waypoint->latitude,
					'symbol' => $symbolInfo ? ($symbolInfo['symbol'] ?? null) : null,
					'offset' => $symbolInfo ? ($symbolInfo['offset'] ?? null) : null,
					'anchor' => $symbolInfo ? ($symbolInfo['anchor'] ?? null) : null,
				],
			];
		}, array_values(array_filter($gpxArray->waypoints, static function(Point $point) {
			// && $point->time !== null;
			return $point->longitude !== null && $point->latitude !== null;
		})));

		return array_merge($trackFeatures, $routeFeatures, $waypointFeatures);
	}

	/**
	 * @param Point $point
	 * @return array|null
	 */
	private function getSymbolInfo(Point $point): ?array {
		if (!$point->symbol) {
			return null;
		}
		$symbol = trim($point->symbol);
		return isset(Application::VALID_WAYPOINT_SYMBOLS[$symbol])
			? [
				'symbol' => $symbol,
				'offset' => Application::VALID_WAYPOINT_SYMBOLS[$symbol]['offset'] ?? null,
				'anchor' => Application::VALID_WAYPOINT_SYMBOLS[$symbol]['anchor'] ?? null,
			]
			: null;
	}

	/**
	 * @param Point $point
	 * @return array
	 */
	public function getGeojsonPoint(Point $point): array {
		return [
			$point->longitude,
			$point->latitude,
			$point->elevation,
			$point->time !== null ? $point->time->getTimestamp() : null,
			$point->extensions !== null ? $point->extensions->toArray() : null,
		];
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @return DataResponse
	 * @throws GenericFileException
	 * @throws LockedException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function processTrackElevations(int $id): DataResponse {
		try {
			$dbTrack = $this->trackMapper->getTrackOfUser($id, $this->userId);
		} catch (DoesNotExistException $e) {
			return new DataResponse('Track not found', Http::STATUS_BAD_REQUEST);
		}

		$path = $dbTrack->getTrackpath();
		$userFolder = $this->root->getUserFolder($this->userId);

		$cleanPath = str_replace(['../', '..\\'], '',  $path);
		if ($userFolder->nodeExists($cleanPath)) {
			$file = $userFolder->get($cleanPath);
			if ($file instanceof File) {
				if ($this->toolsService->endswith($file->getName(), '.GPX') || $this->toolsService->endswith($file->getName(), '.gpx')) {
					$gpxContent = $this->toolsService->remove_utf8_bom($file->getContent());
					$gpxContent = $this->toolsService->sanitizeGpxContent($gpxContent);
					try {
						$gpxContent = $this->conversionService->sanitizeGpxExtensions($gpxContent);
					} catch (Exception | Throwable $e) {
						$this->logger->warning('Error in sanitizeGpxExtensions', ['app' => Application::APP_ID, 'exception' => $e]);
					}
					$gpx = new phpGPX();
					$gpxFile = $gpx->parse($gpxContent);
					try {
						$correctedGpxFile = $this->elevationService->correctElevations($gpxFile);
					} catch (Exception $e) {
						$this->logger->warning('Error in elevation correction', ['app' => Application::APP_ID, 'exception' => $e]);
						return new DataResponse('Elevation correction error: ' . $e->getMessage(), Http::STATUS_BAD_REQUEST);
					}
					// save to dir
					$dirId = $dbTrack->getDirectoryId();
					try {
						$dbDir = $this->directoryMapper->getDirectoryOfUser($dirId, $this->userId);
					} catch (DoesNotExistException $e) {
						return new DataResponse('Directory not found', Http::STATUS_BAD_REQUEST);
					}
					/** @var Folder $targetDirectory */
					$targetDirectory = $userFolder->get($dbDir->getPath());
					if ($this->toolsService->endswith($file->getName(), '.GPX')) {
						$newName = preg_replace('/\.GPX$/', '_corrected.GPX', $file->getName());
					} else {
						$newName = preg_replace('/\.gpx$/', '_corrected.gpx', $file->getName());
					}
					if ($targetDirectory->nodeExists($newName)) {
						/** @var File $targetFile */
						$targetFile = $targetDirectory->get($newName);
						$targetFile->putContent($correctedGpxFile->toXML()->saveXML());
					} else {
						$targetDirectory->newFile($newName, $correctedGpxFile->toXML()->saveXML());
					}
					return new DataResponse('');
				}
			}
		}

		return new DataResponse('Track not found', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Ajax markers json retrieval from DB
	 *
	 * First convert kml, tcx... files if necessary.
	 * Then copy files to a temporary directory (decrypt them if necessary).
	 * Then correct elevations if it was asked.
	 * Then process the files to produce marker content.
	 * Then INSERT or UPDATE the database with processed data.
	 * Then get the markers for all gpx files in the target folder
	 * Then clean useless database entries (for files that no longer exist)
	 *
	 * @param int $id
	 * @param string $directoryPath
	 * @param bool $processAll
	 * @return DataResponse
	 * @throws DoesNotExistException
	 * @throws InvalidPathException
	 * @throws MultipleObjectsReturnedException
	 * @throws NoUserException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\DB\Exception
	 */
	public function getTrackMarkersJson(int $id, string $directoryPath, bool $processAll = false): DataResponse {
		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUser($id ,$this->userId);
		} catch (\OCP\DB\Exception | DoesNotExistException $e) {
			return new DataResponse(['error' => 'No such directory'], Http::STATUS_NOT_FOUND);
		}

		if ($dbDir->getPath() !== $directoryPath) {
			return new DataResponse(['error' => 'No such directory'], Http::STATUS_NOT_FOUND);
		}
		$userFolder = $this->root->getUserFolder($this->userId);

		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUserByPath($directoryPath, $this->userId);
		} catch (\OCP\DB\Exception | DoesNotExistException $e) {
			return new DataResponse(['error' => 'No such directory'], Http::STATUS_NOT_FOUND);
		}
		if ($directoryPath === null || !$userFolder->nodeExists($directoryPath)) {
			return new DataResponse(['error' => 'No such directory'], Http::STATUS_NOT_FOUND);
		}
		$folder = $userFolder->get($directoryPath);
		if (!$folder instanceof Folder) {
			return new DataResponse(['error' => 'This directory is not a directory'], Http::STATUS_BAD_REQUEST);
		}

		$recursive = $dbDir->getRecursive();
		$optionValues = $this->processService->getSharedMountedOptionValue($this->userId);
		$sharedAllowed = $optionValues['sharedAllowed'];
		$mountedAllowed = $optionValues['mountedAllowed'];

		// Convert KML to GPX
		// only if we want to display a folder AND it exists AND we want
		// to compute AND we find GPSBABEL AND file was not already converted

		if ($directoryPath === '/') {
			$directoryPath = '';
		}

		$filesByExtension = [];
		foreach(ConversionService::fileExtToGpsbabelFormat as $ext => $gpsbabel_fmt) {
			$filesByExtension[$ext] = [];
		}

		if ($recursive) {
			$extensions = array_keys(ConversionService::fileExtToGpsbabelFormat);
			$files = $this->processService->searchFilesWithExt($userFolder->get($directoryPath), $sharedAllowed, $mountedAllowed, $extensions);
			foreach ($files as $file) {
				$fileext = '.' . strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
				if ($sharedAllowed || !$file->isShared()) {
					$filesByExtension[$fileext][] = $file;
				}
			}
		} else {
			foreach ($folder->getDirectoryListing() as $ff) {
				if ($ff instanceof File) {
					$ffext = '.' . strtolower(pathinfo($ff->getName(), PATHINFO_EXTENSION));
					if (in_array($ffext, array_keys(ConversionService::fileExtToGpsbabelFormat))) {
						// if shared files are allowed or it is not shared
						if ($sharedAllowed || !$ff->isShared()) {
							$filesByExtension[$ffext][] = $ff;
						}
					}
				}
			}
		}

		$this->conversionService->convertFiles($userFolder, $directoryPath, $this->userId, $filesByExtension);

		// PROCESS gpx files and fill DB
		$this->processService->processGpxFiles($this->userId, $dbDir->getId(), $sharedAllowed, $mountedAllowed, $processAll, $recursive);

		// build tracks array
		$dbTracks = $this->trackMapper->getDirectoryTracksOfUser($this->userId, $dbDir->getId());

		$that = $this;
		$filteredTracks = array_filter($dbTracks, static function(\OCA\GpxPod\Db\Track $dbTrack) use ($userFolder, $sharedAllowed, $that) {
			if ($userFolder->nodeExists($dbTrack->getTrackpath())) {
				$file = $userFolder->get($dbTrack->getTrackpath());
				return $file instanceof File && ($sharedAllowed || !$file->isShared());
			}
			// CLEANUP DB for non-existing files
			$that->trackMapper->delete($dbTrack);
			return false;
		});

		$jsonTracks = array_map(static function(\OCA\GpxPod\Db\Track $track) {
			$jsonTrack = $track->jsonSerialize();
			$jsonTrack['extensions'] = null;
			$jsonTrack['geojson'] = null;
			$jsonTrack['onTop'] = false;
			$jsonTrack['loading'] = false;
			$jsonTrack['color'] = $jsonTrack['color'] ?? '#0693e3';
			$decodedMarker = json_decode($jsonTrack['marker'], true);
			foreach (Application::MARKER_FIELDS as $k => $v) {
				$jsonTrack[$k] = $decodedMarker[$k];
			}
			unset($jsonTrack['marker']);
			return $jsonTrack;
		}, $filteredTracks);

		$tracksById = [];
		foreach ($jsonTracks as $jsonTrack) {
			$tracksById[$jsonTrack['id']] = $jsonTrack;
		}

		$picturesArray = $this->processService->getGeoPicsFromFolder($this->userId, $directoryPath, $id, false);

		return new DataResponse([
			'tracks' => $tracksById,
			'pictures' => $picturesArray,
		]);
	}

	/**
	 * delete from DB all entries referring to absent files
	 * optional parameter : folder to clean
	 */
	private function cleanDbFromAbsentFiles(string $userId, ?int $directoryId = null) {
		$userFolder = $this->root->getUserFolder($userId);

		/** @var \OCA\GpxPod\Db\Track[] $dbDirTracks */
		$dbDirTracks = $directoryId === null
			? $this->trackMapper->getTracksOfUser($userId)
			: $this->trackMapper->getDirectoryTracksOfUser($userId, $directoryId);
		foreach ($dbDirTracks as $dbDirTrack) {
			if ($userFolder->nodeExists($dbDirTrack->getTrackpath())) {
				$node = $userFolder->get($dbDirTrack->getTrackpath());
				if (!$node instanceof File) {
					// not a file
					$this->trackMapper->delete($dbDirTrack);
				}
			} else {
				// does not exist
				$this->trackMapper->delete($dbDirTrack);
			}
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteTrack(int $id): DataResponse {
		return new DataResponse([
			'success' => $this->processService->deleteTrack($this->userId, $id),
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteTracks(array $ids): DataResponse {
		$deleted = [];
		$notDeleted = [];

		foreach ($ids as $id) {
			if ($this->processService->deleteTrack($this->userId, $id)) {
				$deleted[] = $id;
			} else {
				$notDeleted[] = $id;
			}
		}

		return new DataResponse([
			'deleted' => $deleted,
			'not_deleted' => $notDeleted,
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $dirId
	 * @return Response
	 * @throws \OCP\DB\Exception
	 */
	public function getKml(int $dirId): Response {
		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUser($dirId, $this->userId);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			$response = new Response();
			$response->setStatus(Http::STATUS_NOT_FOUND);
			return $response;
		}

		$dirName = basename($dbDir->getPath());
		$kmlData = $this->kmlConversionService->exportDirToKml($this->userId, $dbDir);
		$response = new DataDownloadResponse($kmlData, $dirName . '.GpxPod.kml', 'application/vnd.google-earth.kml+xml');
		return $response;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $dirId
	 * @return Response
	 * @throws \OCP\DB\Exception
	 */
	public function getKmz(int $dirId): Response {
		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUser($dirId, $this->userId);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			$response = new Response();
			$response->setStatus(Http::STATUS_NOT_FOUND);
			return $response;
		}

		$dirName = basename($dbDir->getPath());
		$kmzData = $this->kmlConversionService->exportDirToKmz($this->userId, $dbDir);
		$response = new DataDownloadResponse($kmzData, $dirName . '.GpxPod.kmz', 'application/vnd.google-earth.kmz');
		return $response;
	}
}
