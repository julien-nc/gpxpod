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
		['name' => 'map#getMapTilerFont', 'url' => '/fonts/{fontstack}/{range}.pbf', 'verb' => 'GET'],

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
		['name' => 'page#getGeojson', 'url' => '/tracks/{id}/geojson', 'verb' => 'GET'],
		['name' => 'page#processTrackElevations', 'url' => '/tracks/{id}/elevations', 'verb' => 'GET'],

		['name' => 'oldPage#index', 'url' => '/old-ui', 'verb' => 'GET'],

		['name' => 'oldPage#delDirectory', 'url' => '/deldirectory', 'verb' => 'POST'],
		['name' => 'oldPage#getgpx', 'url' => '/getgpx', 'verb' => 'POST'],
		['name' => 'oldPage#getpublicgpx', 'url' => '/getpublicgpx', 'verb' => 'POST'],
		['name' => 'oldPage#getTrackMarkersText', 'url' => '/tracks-old', 'verb' => 'POST'],
		['name' => 'oldPage#publicFile', 'url' => '/publicFile', 'verb' => 'GET'],
		['name' => 'oldPage#publicFolder', 'url' => '/publicFolder', 'verb' => 'GET'],
		['name' => 'oldPage#isFileShareable', 'url' => '/isFileShareable', 'verb' => 'POST'],
		['name' => 'oldPage#isFolderShareable', 'url' => '/isFolderShareable', 'verb' => 'POST'],
		['name' => 'oldPage#deleteTracks', 'url' => '/deleteTracks', 'verb' => 'POST'],

		['name' => 'comparison#comparePageGet', 'url' => '/compare', 'verb' => 'GET'],
		['name' => 'comparison#comparePagePost', 'url' => '/compare', 'verb' => 'POST'],

		['name' => 'oldComparison#gpxvcomp', 'url' => '/gpxvcomp', 'verb' => 'GET'],
		['name' => 'oldComparison#gpxvcompp', 'url' => '/gpxvcompp', 'verb' => 'POST'],

		['name' => 'utils#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		// tile servers
		['name' => 'utils#addTileServer', 'url' => '/tileservers', 'verb' => 'POST'],
		['name' => 'utils#deleteTileServer', 'url' => '/tileservers/{id}', 'verb' => 'DELETE'],
		['name' => 'utils#adminAddTileServer', 'url' => '/admin/tileservers', 'verb' => 'POST'],
		['name' => 'utils#AdminDeleteTileServer', 'url' => '/admin/tileservers/{id}', 'verb' => 'DELETE'],
		// old tile servers
		['name' => 'utils#oldAddTileServer', 'url' => '/addTileServer', 'verb' => 'POST'],
		['name' => 'utils#oldDeleteTileServer', 'url' => '/deleteTileServer', 'verb' => 'POST'],

		['name' => 'utils#cleanMarkersAndGeojsons', 'url' => '/cleanMarkersAndGeojsons', 'verb' => 'POST'],
		['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
		['name' => 'utils#saveOptionValue', 'url' => '/saveOptionValue', 'verb' => 'PUT'],
		['name' => 'utils#saveOptionValues', 'url' => '/saveOptionValues', 'verb' => 'PUT'],
		['name' => 'utils#moveTracks', 'url' => '/moveTracks', 'verb' => 'POST'],
		['name' => 'utils#cleanDb', 'url' => '/cleanDb', 'verb' => 'POST'],
	]
];
