<?php
/**
 * ownCloud - gpxpod
 *
 *
 * @author
 *
 * @copyright
 */

namespace OCA\GpxPod\AppInfo;



use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

use OCA\GpxPod\Controller\PageController;

/**
 * Class Application
 *
 * @package OCA\GpxPod\AppInfo
 */
class Application extends App {

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct('gpxpod', $urlParams);

		$container = $this->getContainer();

		/**
		 * Controllers
		 */
		$container->registerService(
			'PageController', function (IContainer $c) {
			return new PageController(
				$c->query('AppName'),
				$c->query('Request'),
                $c->query('UserId'),
                $c->getServer()->getUserFolder($c->query('UserId')),
                $c->query('OCP\IConfig'),
                $c->getServer()->getShareManager()
			);
		}
		);

		$container->registerService(
			'UserFolder', function (IAppContainer $c) {
			return $c->getServer()
					 ->getUserFolder($c->query('UserId'));
		}
		);

	}

}

