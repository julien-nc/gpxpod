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
		$geocoderUrl = $this->appConfig->getValueString(Application::APP_ID, 'geocoder_url', lazy: true);
		$adminMaptilerApiKey = $this->appConfig->getValueString(Application::APP_ID, 'maptiler_api_key', lazy: true);
		$useGpsbabel = $this->appConfig->getValueString(Application::APP_ID, 'use_gpsbabel', '0', lazy: true) === '1';
		$proxyOsm = $this->appConfig->getValueString(Application::APP_ID, 'proxy_osm', '1', lazy: true) === '1';
		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);

		$adminConfig = [
			'geocoder_url' => $geocoderUrl,
			// do not expose the stored value to the user
			'maptiler_api_key' => $adminMaptilerApiKey ? 'dummyApiKey' : '',
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
