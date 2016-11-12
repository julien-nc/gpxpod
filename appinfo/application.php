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
use OCA\GpxPod\Controller\ComparisonController;
use OCA\GpxPod\Controller\UtilsController;

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
            'PageController', function (IAppContainer $c) {
                return new PageController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig(),
                    $c->getServer()->getShareManager(),
                    $c->getServer()->getPreviewManager()
                );
            }
        );

        $container->registerService(
            'ComparisonController', function (IAppContainer $c) {
                return new ComparisonController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    //$c->getServer()->getUserFolder($c->query('UserId')),
                    //$c->query('OCP\IConfig'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig()
                );
            }
        );

        $container->registerService(
            'UtilsController', function (IAppContainer $c) {
                return new UtilsController(
                    $c->query('AppName'),
                    $c->query('Request'),
                    $c->query('UserId'),
                    //$c->getServer()->getUserFolder($c->query('UserId')),
                    //$c->query('OCP\IConfig'),
                    $c->query('ServerContainer')->getUserFolder($c->query('UserId')),
                    $c->query('ServerContainer')->getConfig()
                );
            }
        );

    }

}

