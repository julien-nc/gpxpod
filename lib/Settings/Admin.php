<?php
namespace OCA\GpxPod\Settings;

use OCA\GpxPod\Db\TileServerMapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\GpxPod\AppInfo\Application;

class Admin implements ISettings {

	public function __construct(private IConfig       $config,
								private TileServerMapper $tileServerMapper,
								private IInitialState $initialStateService) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$adminMaptilerApiKey = $this->config->getAppValue(Application::APP_ID, 'maptiler_api_key', Application::DEFAULT_MAPTILER_API_KEY) ?: Application::DEFAULT_MAPTILER_API_KEY;
		$useGpsbabel = $this->config->getAppValue(Application::APP_ID, 'use_gpsbabel', '0') === '1';
		$adminTileServers = $this->tileServerMapper->getTileServersOfUser(null);

		$adminConfig = [
			'maptiler_api_key' => $adminMaptilerApiKey,
			'use_gpsbabel' => $useGpsbabel,
			'extra_tile_servers' => $adminTileServers,
		];
		$this->initialStateService->provideInitialState('admin-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 10;
	}
}
