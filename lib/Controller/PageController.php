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
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OC\User\NoUserException;
use OCA\GpxPod\Service\MapService;
use OCP\Files\File;
use OCA\GpxPod\AppInfo\Application;

use OCA\GpxPod\Db\Directory;
use OCA\GpxPod\Db\DirectoryMapper;
use OCA\GpxPod\Db\TrackMapper;
use OCA\GpxPod\Service\ConversionService;
use OCA\GpxPod\Service\ElevationService;
use OCA\GpxPod\Service\ProcessService;
use OCA\GpxPod\Service\ToolsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Http\Client\IClientService;
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
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

class PageController extends Controller {

	private IConfig $config;
	private IInitialState $initialStateService;
	private IRootFolder $root;
	private IClientService $clientService;
	private ProcessService $processService;
	private ConversionService $conversionService;
	private ToolsService $toolsService;
	private ElevationService $elevationService;
	private DirectoryMapper $directoryMapper;
	private TrackMapper $trackMapper;
	private IManager $shareManager;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private ?string $userId;
	/**
	 * @var array|string[]
	 */
	private array $extensions;
	private array $upperExtensions;
	private MapService $mapService;

	public function __construct($appName,
								IRequest $request,
								IConfig $config,
								IInitialState $initialStateService,
								IRootFolder $root,
								IClientService $clientService,
								ProcessService $processService,
								ConversionService $conversionService,
								ToolsService $toolsService,
								ElevationService $elevationService,
								MapService $mapService,
								DirectoryMapper $directoryMapper,
								TrackMapper $trackMapper,
								IManager $shareManager,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								?string $userId) {
		parent::__construct($appName, $request);

		$this->extensions = [
			'.kml' => 'kml',
			'.gpx' => '',
			'.tcx' => 'gtrnctr',
			'.igc' => 'igc',
			'.jpg' => '',
			'.fit' => 'garmin_fit',
		];
		$this->upperExtensions = array_map('strtoupper', array_keys($this->extensions));

		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->root = $root;
		$this->clientService = $clientService;
		$this->processService = $processService;
		$this->conversionService = $conversionService;
		$this->toolsService = $toolsService;
		$this->elevationService = $elevationService;
		$this->directoryMapper = $directoryMapper;
		$this->trackMapper = $trackMapper;
		$this->shareManager = $shareManager;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->userId = $userId;
		$this->mapService = $mapService;
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
		$adminMapboxApiKey = $this->config->getAppValue(Application::APP_ID, 'mapbox_api_key', Application::DEFAULT_MAPBOX_API_KEY) ?: Application::DEFAULT_MAPBOX_API_KEY;
		$mapboxApiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'mapbox_api_key', $adminMapboxApiKey) ?: $adminMapboxApiKey;
		$settings['mapbox_api_key'] = $mapboxApiKey;

		$settings = $this->getDefaultSettings($settings);

		$dirObj = [];
		foreach ($alldirs as $dir) {
			$dirObj[$dir['id']] = [
				'id' => $dir['id'],
				'path' => $dir['path'],
				'isOpen' => $dir['isOpen'],
				'sortOrder' => $dir['sortOrder'],
				'tracks' => [],
				'pictures' => [],
				'loading' => false,
			];
		}

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
		$this->addPageCsp($csp);
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
	 * @return Response
	 * @throws NotFoundException
	 */
	public function publicPasswordIndex(string $shareToken, string $password): Response {
		return $this->publicIndex($shareToken, $password);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @param string $shareToken
	 * @param string|null $password
	 * @return Response
	 * @throws NotFoundException
	 */
	public function publicIndex(string $shareToken, ?string $password = null): Response {
		// check if share exists
		try {
			$share = $this->shareManager->getShareByToken($shareToken);
		} catch (ShareNotFound $e) {
			return new TemplateResponse('core', '404', [], 'guest');
		}
		// check if share is password protected
		$sharePassword = $share->getPassword();
		if ($sharePassword && !$this->shareManager->checkPassword($share, $password)) {
			// if so: return password form template response
			$params = [
				'action' => $this->urlGenerator->linkToRouteAbsolute('gpxpod.page.publicIndex', ['shareToken' => $shareToken]),
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
			return $response;
		}

		// if not: return real public index with initial state
		return $this->getPublicTemplate($share, $password);
	}

	/**
	 * @param IShare $share
	 * @param string|null $password
	 * @return PublicTemplateResponse
	 * @throws NotFoundException
	 */
	private function getPublicTemplate(IShare $share, ?string $password): PublicTemplateResponse {
		$shareOwner = $share->getShareOwner();
		$adminMaptilerApiKey = $this->config->getAppValue(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$maptilerApiKey = $this->config->getUserValue($shareOwner, Application::APP_ID, 'maptiler_api_key', $adminMaptilerApiKey) ?: $adminMaptilerApiKey;
		$adminMapboxApiKey = $this->config->getAppValue(Application::APP_ID, 'mapbox_api_key', Application::DEFAULT_MAPBOX_API_KEY) ?: Application::DEFAULT_MAPBOX_API_KEY;
		$mapboxApiKey = $this->config->getUserValue($shareOwner, Application::APP_ID, 'mapbox_api_key', $adminMapboxApiKey) ?: $adminMapboxApiKey;
		$settings = [
			'show_mouse_position_control' => '1',
			'show_marker_cluster' => '0',
			'maptiler_api_key' => $maptilerApiKey,
			'mapbox_api_key' => $mapboxApiKey,
		];
		$settings = $this->getDefaultSettings($settings);
		$state = [
			'shareToken' => $share->getToken(),
			'directories' => [],
			'settings' => $settings,
		];
		if ($password !== null) {
			$state['sharePassword'] = $password;
		}

		$node = $share->getNode();
		if ($node instanceof File) {
			$state['shareTargetType'] = 'file';
			$state['directories'] = [
				$share->getToken() => [
					'id' => $share->getToken(),
					'path' => $this->l10n->t('Public link'),
					'isOpen' => true,
					'sortOrder' => 0,
					'tracks' => [
						'0' => $this->getPublicTrack($share),
					],
					'pictures' => [],
					'loading' => false,
				],
			];
		} elseif ($node instanceof Folder) {
			$state['shareTargetType'] = 'folder';
			$state['directories'] = [
				$share->getToken() => [
					'id' => $share->getToken(),
					'path' => $share->getNode()->getName(),
					'isOpen' => true,
					'sortOrder' => 0,
					'tracks' => $this->getPublicDirectoryTracks($share),
					'pictures' => [],
					'loading' => false,
				],
			];
		}

		$this->initialStateService->provideInitialState(
			'gpxpod-state',
			$state
		);

		$response = new PublicTemplateResponse(Application::APP_ID, 'newMain');
		$response->setHeaderTitle($share->getNode()->getName());
		$response->setHeaderDetails(
			$node instanceof File
				? $this->l10n->t('GpxPod public file share')
				: $this->l10n->t('GpxPod public directory share')
		);
		$response->setFooterVisible(false);
		$csp = new ContentSecurityPolicy();
		$this->addPageCsp($csp);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	/**
	 * @param ContentSecurityPolicy $csp
	 * @return void
	 */
	private function addPageCsp(ContentSecurityPolicy $csp): void {
		$csp
			// raster tiles
			->addAllowedConnectDomain('https://*.tile.openstreetmap.org')
			->addAllowedConnectDomain('https://server.arcgisonline.com')
			->addAllowedConnectDomain('https://stamen-tiles.a.ssl.fastly.net')
			// vector tiles
			->addAllowedImageDomain('https://api.maptiler.com')
			->addAllowedConnectDomain('https://api.maptiler.com')
			->addAllowedConnectDomain('https://api.mapbox.com')
			->addAllowedConnectDomain('https://events.mapbox.com')
			// nominatim
			->addAllowedConnectDomain('https://nominatim.openstreetmap.org')
			// maplibre-gl
			->addAllowedWorkerSrcDomain('blob:');
	}

	private function getPublicDirectoryTracks(IShare $share): array {
		$sharedBy = $share->getSharedBy();
		$sharedDir = $share->getNode();
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
				$jsonTrack[$k] = $decodedMarker[$v];
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
			return new DataResponse('', Http::STATUS_NOT_FOUND);
		}
		// check share password
		$sharePassword = $share->getPassword();
		if ($sharePassword && !$this->shareManager->checkPassword($share, $password)) {
			return new DataResponse('p', Http::STATUS_NOT_FOUND);
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
		if ($dbDir->getPath() !== $sharedDirPath) {
			return new DataResponse('s', Http::STATUS_NOT_FOUND);
		}

		$userFolder = $this->root->getUserFolder($sharedBy);
		$cleanpath = str_replace(['../', '..\\'], '',  $dbTrack->getTrackpath());
		if ($userFolder->nodeExists($cleanpath)) {
			$file = $userFolder->get($cleanpath);
			if ($file instanceof File) {
				if ($this->toolsService->endswith($file->getName(), '.GPX') || $this->toolsService->endswith($file->getName(), '.gpx')) {
					$gpxContent = $this->toolsService->remove_utf8_bom($file->getContent());
					$geojsonArray = $this->gpxToGeojson($gpxContent);
					return new DataResponse($geojsonArray);
				}
			}
		}

		return new DataResponse('e', Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @param IShare $share
	 * @return array
	 */
	private function getPublicTrack(IShare $share): array {
		$sharedBy = $share->getSharedBy();
		$trackFile = $share->getNode();
		$trackPath = preg_replace('/^files/', '', $trackFile->getInternalPath());
//		try {
			$track = $this->trackMapper->getTrackOfUserByPath($sharedBy, $trackPath);
//		} catch (DoesNotExistException $e) {
//			 TODO process the parent directory (problem, we now pass dirId to processService->processGpxFiles())
//		}
		$jsonTrack = $track->jsonSerialize();
		$jsonTrack['id'] = 0;
		$jsonTrack['isEnabled'] = true;

		$gpxContent = $this->toolsService->remove_utf8_bom($trackFile->getContent());
		$geojsonArray = $this->gpxToGeojson($gpxContent);
		$jsonTrack['geojson'] = $geojsonArray;

		$jsonTrack['onTop'] = false;
		$jsonTrack['loading'] = false;
		$jsonTrack['directoryId'] = $share->getToken();
		$jsonTrack['trackpath'] = basename($jsonTrack['trackpath']);
		$jsonTrack['color'] = $jsonTrack['color'] ?? '#0693e3';
		$decodedMarker = json_decode($jsonTrack['marker'], true);
		foreach (Application::MARKER_FIELDS as $k => $v) {
			$jsonTrack[$k] = $decodedMarker[$v];
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
		return $settings;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param bool $isOpen
	 * @param int|null $sortOrder
	 * @return DataResponse
	 * @throws \OCP\DB\Exception
	 */
	public function updateDirectory(int $id, ?bool $isOpen = null, ?int $sortOrder = null): DataResponse {
		$this->directoryMapper->updateDirectory($id, $this->userId, null, $isOpen, $sortOrder);
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

		$cleanpath = str_replace(['../', '..\\'], '', $path);
		if ($userFolder->nodeExists($cleanpath)) {
			try {
				$dir = $this->directoryMapper->createDirectory($cleanpath, $this->userId, false);
				$addedId = $dir->getId();
			} catch (\OCP\DB\Exception $e) {
				return new DataResponse('Impossible to insert. ' . $e->getMessage(), 400);
			}
			return new DataResponse($addedId);
		} else {
			return new DataResponse($cleanpath . ' does not exist', 400);
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

		$cleanpath = str_replace(['../', '..\\'], '',  $path);
		if ($userFolder->nodeExists($cleanpath)) {
			$folder = $userFolder->get($cleanpath);

			// DIRS array population
			$optionValues = $this->processService->getSharedMountedOptionValue($this->userId);
			$sharedAllowed = $optionValues['sharedAllowed'];
			$mountedAllowed = $optionValues['mountedAllowed'];
			$showpicsonlyfold = $this->config->getUserValue($this->userId, 'gpxpod', 'showpicsonlyfold', 'true');
			$searchJpg = ($showpicsonlyfold === 'true');
			$extensions = array_keys($this->extensions);
			if ($searchJpg) {
				$extensions = array_merge($extensions, ['.jpg']);
			}
			$files = $this->processService->searchFilesWithExt($folder, $sharedAllowed, $mountedAllowed, $extensions);
			$alldirs = [];
			foreach($files as $file) {
				if ($file->getType() === \OCP\Files\FileInfo::TYPE_FILE and
					// name extension is supported
					(
						in_array( '.'.pathinfo($file->getName(), PATHINFO_EXTENSION), array_keys($this->extensions)) or
						in_array( '.'.pathinfo($file->getName(), PATHINFO_EXTENSION), $this->upperExtensions)
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
			return new DataResponse($cleanpath . ' does not exist', 400);
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

		$cleanpath = str_replace(['../', '..\\'], '',  $path);
		if ($userFolder->nodeExists($cleanpath)) {
			$file = $userFolder->get($cleanpath);
			if ($file instanceof File) {
				if ($this->toolsService->endswith($file->getName(), '.GPX') || $this->toolsService->endswith($file->getName(), '.gpx')) {
					$gpxContent = $this->toolsService->remove_utf8_bom($file->getContent());
					$geojsonArray = $this->gpxToGeojson($gpxContent);
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
		$gpxContent = $this->processService->sanitizeGpxContent($gpxContent);
		$gpx = new phpGPX();
		$gpxArray = $gpx->parse($gpxContent);

		// one LineString per segment, ignoring the track separation
		/*
		$result = [
			'type' => 'FeatureCollection',
			'features' => [],
		];
		foreach ($gpxArray->tracks as $track) {
			foreach ($track->segments as $segment) {
				$result['features'][] = [
					'type' => 'Feature',
					'geometry' => [
						'type' => 'LineString',
    					'coordinates' => array_map(static function(Point $point) {
								return [
									$point->longitude,
									$point->latitude,
									$point->elevation,
									$point->time->getTimestamp(),
								];
							}, array_values(array_filter($segment->points, static function(Point $point) {
								return $point->longitude !== null && $point->latitude !== null && $point->time !== null;
							}))
						),
					],
				];
			}
		}
		return $result;
		*/

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
				],
			];
		}, $gpxArray->routes);

		// one point per waypoint
		$waypointFeatures = array_map(function(Point $waypoint) {
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
	 * @return array
	 */
	public function getGeojsonPoint(Point $point): array {
		return [
			$point->longitude,
			$point->latitude,
			$point->elevation,
			$point->time !== null ? $point->time->getTimestamp() : null,
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

		$cleanpath = str_replace(['../', '..\\'], '',  $path);
		if ($userFolder->nodeExists($cleanpath)) {
			$file = $userFolder->get($cleanpath);
			if ($file instanceof File) {
				if ($this->toolsService->endswith($file->getName(), '.GPX') || $this->toolsService->endswith($file->getName(), '.gpx')) {
					$gpxContent = $this->toolsService->remove_utf8_bom($file->getContent());
					$gpx = new phpGPX();
					$gpxFile = $gpx->parse($gpxContent);
					try {
						$correctedGpxFile = $this->elevationService->correctElevations($gpxFile);
					} catch (Exception $e) {
						return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
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
	 */
	public function getTrackMarkersJson(int $id, string $directoryPath, bool $processAll = false): DataResponse {
		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUser($id ,$this->userId);
		} catch (\OCP\DB\Exception | DoesNotExistException $e) {
			return new DataResponse('No such directory', 400);
		}

		if ($dbDir->getPath() !== $directoryPath) {
			return new DataResponse('No such directory', 400);
		}
		$userFolder = $this->root->getUserFolder($this->userId);

		try {
			$dbDir = $this->directoryMapper->getDirectoryOfUserByPath($directoryPath, $this->userId);
		} catch (\OCP\DB\Exception | DoesNotExistException $e) {
			return new DataResponse('No such directory', 400);
		}
		if ($directoryPath === null || !$userFolder->nodeExists($directoryPath)) {
			return new DataResponse('No such directory', 400);
		}
		$folder = $userFolder->get($directoryPath);
		if (!$folder instanceof Folder) {
			return new DataResponse('Directory is not a directory', 400);
		}

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
		foreach($this->extensions as $ext => $gpsbabel_fmt) {
			$filesByExtension[$ext] = [];
		}

		foreach ($folder->getDirectoryListing() as $ff) {
			if ($ff instanceof File) {
				$ffext = '.' . strtolower(pathinfo($ff->getName(), PATHINFO_EXTENSION));
				if (in_array($ffext, array_keys($this->extensions))) {
					// if shared files are allowed or it is not shared
					if ($sharedAllowed || !$ff->isShared()) {
						$filesByExtension[$ffext][] = $ff;
					}
				}
			}
		}

		$this->convertFiles($userFolder, $directoryPath, $this->userId, $filesByExtension);

		// PROCESS gpx files and fill DB
		$this->processService->processGpxFiles($this->userId, $dbDir->getId(), $sharedAllowed, $mountedAllowed, $processAll);

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
			$jsonTrack['geojson'] = null;
			$jsonTrack['onTop'] = false;
			$jsonTrack['loading'] = false;
			$jsonTrack['color'] = $jsonTrack['color'] ?? '#0693e3';
			$decodedMarker = json_decode($jsonTrack['marker'], true);
			foreach (Application::MARKER_FIELDS as $k => $v) {
				$jsonTrack[$k] = $decodedMarker[$v];
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
	 * delete from DB all entries refering to absent files
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
}
