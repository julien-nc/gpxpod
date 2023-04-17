<?php
/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\GpxPod\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;

use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\GpxPod\Listener\AddFilesScriptsListener;
use OCA\GpxPod\Listener\FilesSharingAddScriptsListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

class Application extends App implements IBootstrap {

	public const APP_ID = 'gpxpod';
	public const USER_AGENT = 'Nextcloud Gpxpod app';

	public const DEFAULT_MAPTILER_API_KEY = 'get_your_own_OpIi9ZULNHzrESv6T2vL';

	public const TILE_SERVER_RASTER = 0;
	public const TILE_SERVER_VECTOR = 1;

	public const COLOR_CRITERIAS = [
		'none' => 0,
		'elevation' => 1,
		'speed' => 2,
		'pace' => 3,
	];

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

	public const VALID_WAYPOINT_SYMBOLS = [
		'Bar' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Bike Trail' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Block, Blue' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Block, Green' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Block, Red' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Campground' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Contact, Alien' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Contact, Big Ears' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Contact, Cat' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Contact, Dog' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Contact, Female3' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Blue Diamond' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Green Diamond' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Red Diamond' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Dot, White' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Drinking Water' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Flag, Blue' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Flag, Green' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Flag, Red' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Pin, Blue' => ['offset' => [-2, 0], 'anchor' => 'bottom-left'],
		'Pin, Green' => ['offset' => [-2, 0], 'anchor' => 'bottom-left'],
		'Pin, Red' => ['offset' => [-2, 0], 'anchor' => 'bottom-left'],
		'Geocache Found' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Geocache' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Trail Head' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Medical Facility' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Residence' => ['offset' => [0, 0], 'anchor' => 'center'],
		'Skull and Crossbones' => ['offset' => [0, 0], 'anchor' => 'center'],
	];

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, AddFilesScriptsListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, FilesSharingAddScriptsListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}

