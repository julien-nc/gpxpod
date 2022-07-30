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

namespace OCA\GpxPod\AppInfo;

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

	public const DEFAULT_MAPTILER_API_KEY = 'get_your_own_OpIi9ZULNHzrESv6T2vL';
	public const DEFAULT_MAPBOX_API_KEY = 'pk.eyJ1IjoiZW5laWx1aiIsImEiOiJjazE4Y2xvajcxbGJ6M29xajY1bThuNjRnIn0.hZ4f0_kiPK5OvLBQ1GxVmg';

	public const MARKER_FIELDS = [
		'lat' => 0,
		'lon' => 1,
		'folder' => 2,
		'name' => 3,
		'total_distance' => 4,
		'total_duration' => 5,
		'date_begin' => 6,
		'date_end' => 7,
		'positive_elevation_gain' => 8,
		'negative_elevation_gain' => 9,
		'min_elevation' => 10,
		'max_elevation' => 11,
		'max_speed' => 12,
		'average_speed' => 13,
		'moving_time' => 14,
		'stopped_time' => 15,
		'moving_average_speed' => 16,
		'north' => 17,
		'south' => 18,
		'east' => 19,
		'west' => 20,
		'short_point_list' => 21,
		'track_name_list' => 22,
		'link_url' => 23,
		'link_text' => 24,
		'moving_pace' => 25,
	];

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

