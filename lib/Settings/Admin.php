<?php

namespace OCA\GpxPod\Settings;

use OCA\GpxPod\AppInfo\Application;
use OCA\GpxPod\Db\TileServerMapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\DB\Exception;
use OCP\IAppConfig;

use OCP\Settings\ISettings;

class Admin implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private TileServerMapper $tileServerMapper,
		private IInitialState $initialStateService,
	) {
	}

	/**
	 * @return TemplateResponse
	 * @throws Exception
	 */
	public function getForm(): TemplateResponse {
		// $adminMaptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$useGpsbabel = $this->appConfig->getValueString(Application::APP_ID, 'use_gpsbabel', '0') === '1';
		$proxyOsm = $this->appConfig->getValueString(Application::APP_ID, 'proxy_osm', '1') === '1';
		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);

		$adminConfig = [
			// do not expose the stored value to the user
			'maptiler_api_key' => 'dummyApiKey',
			'use_gpsbabel' => $useGpsbabel,
			'proxy_osm' => $proxyOsm,
			'extra_tile_servers' => $adminTileServers,
		];
		$this->initialStateService->provideInitialState('admin-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'gpxpod';
	}

	public function getPriority(): int {
		return 10;
	}
}
