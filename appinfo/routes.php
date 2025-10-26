<?php

/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2015
 */

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\GpxPod\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#publicIndex', 'url' => '/s/{shareToken}', 'verb' => 'GET'],
		['name' => 'page#publicPasswordIndex', 'url' => '/s/{shareToken}', 'verb' => 'POST'],
		['name' => 'page#getPublicDirectoryTrackGeojson', 'url' => '/s/{shareToken}/tracks/{trackId}/geojson', 'verb' => 'GET'],

		['name' => 'map#getRasterTile', 'url' => '/tiles/{service}/{x}/{y}/{z}', 'verb' => 'GET'],
		['name' => 'map#nominatimSearch', 'url' => '/nominatim/search', 'verb' => 'GET'],
		['name' => 'map#getMapTilerStyle', 'url' => '/maptiler/maps/{version}/style.json', 'verb' => 'GET'],
		['name' => 'map#getMapTilerFont', 'url' => '/maptiler/fonts/{fontstack}/{range}.pbf', 'verb' => 'GET'],
		['name' => 'map#getMapTilerTiles', 'url' => '/maptiler/tiles/{version}/tiles.json', 'verb' => 'GET'],
		['name' => 'map#getMapTilerTile', 'url' => '/maptiler/tiles/{version}/{z}/{x}/{y}.{ext}', 'verb' => 'GET'],
		['name' => 'map#getMapTilerSpriteNoSize', 'url' => '/maptiler/maps/{version}/sprite.{ext}', 'verb' => 'GET'],
		['name' => 'map#getMapTilerSprite', 'url' => '/maptiler/maps/{version}/sprite{size}.{ext}', 'verb' => 'GET'],
		['name' => 'map#getMapTilerResource', 'url' => '/maptiler/resources/{name}', 'verb' => 'GET'],

		['name' => 'page#addDirectory', 'url' => '/directories', 'verb' => 'POST'],
		['name' => 'page#updateDirectory', 'url' => '/directories/{id}', 'verb' => 'PUT'],
		['name' => 'page#updateDirectoryTracks', 'url' => '/directories/{id}/tracks', 'verb' => 'PUT'],
		['name' => 'page#deleteDirectory', 'url' => '/directories/{id}', 'verb' => 'DELETE'],
		['name' => 'page#getTrackMarkersJson', 'url' => '/directories/{id}/tracks', 'verb' => 'GET'],
		['name' => 'page#getKml', 'url' => '/directories/{dirId}/kml', 'verb' => 'GET'],
		['name' => 'page#getKmz', 'url' => '/directories/{dirId}/kmz', 'verb' => 'GET'],
		['name' => 'page#updateTrack', 'url' => '/tracks/{id}', 'verb' => 'PUT'],
		['name' => 'page#deleteTrack', 'url' => '/tracks/{id}', 'verb' => 'DELETE'],
		['name' => 'page#deleteTracks', 'url' => '/tracks', 'verb' => 'DELETE'],
		['name' => 'page#cutTrack', 'url' => '/tracks/{id}/cut', 'verb' => 'POST'],
		['name' => 'page#getGeojson', 'url' => '/tracks/{id}/geojson', 'verb' => 'GET'],
		['name' => 'page#processTrackElevations', 'url' => '/tracks/{id}/elevations', 'verb' => 'GET'],

		['name' => 'comparison#comparePageGet', 'url' => '/compare', 'verb' => 'GET'],
		['name' => 'comparison#comparePagePost', 'url' => '/compare', 'verb' => 'POST'],

		['name' => 'utils#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'utils#setSensitiveAdminConfig', 'url' => '/admin-config/sensitive', 'verb' => 'PUT'],

		// tile servers
		['name' => 'utils#addTileServer', 'url' => '/tileservers', 'verb' => 'POST'],
		['name' => 'utils#deleteTileServer', 'url' => '/tileservers/{id}', 'verb' => 'DELETE'],
		['name' => 'utils#adminAddTileServer', 'url' => '/admin/tileservers', 'verb' => 'POST'],
		['name' => 'utils#AdminDeleteTileServer', 'url' => '/admin/tileservers/{id}', 'verb' => 'DELETE'],

		['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
		['name' => 'utils#saveOptionValue', 'url' => '/saveOptionValue', 'verb' => 'PUT'],
		['name' => 'utils#saveOptionValues', 'url' => '/saveOptionValues', 'verb' => 'PUT'],
		['name' => 'utils#cleanDb', 'url' => '/cleanDb', 'verb' => 'POST'],
	]
];
