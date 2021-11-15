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

use OCP\IContainer;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\Util;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IL10N;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
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

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
		$container = $this->getContainer();
		$server = $container->getServer();

		$eventDispatcher = $server->getEventDispatcher();
		$eventDispatcher->addListener(LoadAdditionalScriptsEvent::class, function() {
			Util::addScript(self::APP_ID, 'filetypes');
			Util::addStyle(self::APP_ID, 'style');
		});
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}

