<?php
/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Gpxpod\AppInfo;

use OCP\EventDispatcher\IEventDispatcher;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\Util;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

/**
 * Class Application
 *
 * @package OCA\Cospend\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'gpxpod';

	public const DEFAULT_MAPTILER_API_KEY = 'wm3JmgmrSAMz79ffXveo';
	public const DEFAULT_MAPBOX_API_KEY = 'pk.eyJ1IjoiZW5laWx1aiIsImEiOiJjazE4Y2xvajcxbGJ6M29xajY1bThuNjRnIn0.hZ4f0_kiPK5OvLBQ1GxVmg';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
		$container = $this->getContainer();

		$eventDispatcher = $container->get(IEventDispatcher::class);
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, static function() {
			Util::addScript(self::APP_ID, self::APP_ID . '-filetypes');
			Util::addStyle(self::APP_ID, 'style');
		});
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}

