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

        $eventDispatcher = \OC::$server->getEventDispatcher();
        $eventDispatcher->addListener('OCA\Files::loadAdditionalScripts', function() {
            \OCP\Util::addScript(self::APP_ID, 'filetypes');
            \OCP\Util::addStyle(self::APP_ID, 'style');
        });

        $container = $this->getContainer();

        $container->query(\OCP\INavigationManager::class)->add(function () use ($container) {
            $urlGenerator = $container->query(\OCP\IURLGenerator::class);
            $l10n = $container->query(\OCP\IL10N::class);
            return [
                'id' => self::APP_ID,

                'order' => 10,

                // the route that will be shown on startup
                'href' => $urlGenerator->linkToRoute('gpxpod.page.index'),

                // the icon that will be shown in the navigation
                // this file needs to exist in img/
                'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),

                // the title of your application. This will be used in the
                // navigation or on the settings page of your app
                'name' => $l10n->t('GpxPod'),
            ];
        });
    }

    public function register(IRegistrationContext $context): void {
    }

    public function boot(IBootContext $context): void {
    }

}

