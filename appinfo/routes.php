<?php
/**
 * Nextcloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

		['name' => 'page#getRasterTile', 'url' => '/tiles/{service}/{x}/{y}/{z}', 'verb' => 'GET'],
		['name' => 'page#addDirectory', 'url' => '/directory', 'verb' => 'POST'],
		['name' => 'page#updateDirectory', 'url' => '/directories/{id}', 'verb' => 'PUT'],
		['name' => 'page#deleteDirectory', 'url' => '/directory/{id}', 'verb' => 'DELETE'],
		['name' => 'page#getTrackMarkersJson', 'url' => '/tracks', 'verb' => 'POST'],
		['name' => 'page#updateTrack', 'url' => '/tracks/{id}', 'verb' => 'PUT'],
		['name' => 'page#getGeojson', 'url' => '/tracks/{id}/geojson', 'verb' => 'GET'],

		['name' => 'oldPage#index', 'url' => '/old-ui', 'verb' => 'GET'],

		['name' => 'oldPage#delDirectory', 'url' => '/deldirectory', 'verb' => 'POST'],
		['name' => 'oldPage#getgpx', 'url' => '/getgpx', 'verb' => 'POST'],
		['name' => 'oldPage#getpublicgpx', 'url' => '/getpublicgpx', 'verb' => 'POST'],
		['name' => 'oldPage#getTrackMarkersText', 'url' => '/tracks-old', 'verb' => 'POST'],
		['name' => 'oldPage#processTrackElevations', 'url' => '/processTrackElevations', 'verb' => 'POST'],
		['name' => 'oldPage#publicFile', 'url' => '/publicFile', 'verb' => 'GET'],
		['name' => 'oldPage#publicFolder', 'url' => '/publicFolder', 'verb' => 'GET'],
		['name' => 'oldPage#isFileShareable', 'url' => '/isFileShareable', 'verb' => 'POST'],
		['name' => 'oldPage#isFolderShareable', 'url' => '/isFolderShareable', 'verb' => 'POST'],
		['name' => 'oldPage#deleteTracks', 'url' => '/deleteTracks', 'verb' => 'POST'],

		['name' => 'comparison#gpxvcomp', 'url' => '/gpxvcomp', 'verb' => 'GET'],
		['name' => 'comparison#gpxvcompp', 'url' => '/gpxvcompp', 'verb' => 'POST'],

		['name' => 'utils#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'utils#cleanMarkersAndGeojsons', 'url' => '/cleanMarkersAndGeojsons', 'verb' => 'POST'],
		['name' => 'utils#addTileServer', 'url' => '/addTileServer', 'verb' => 'POST'],
		['name' => 'utils#deleteTileServer', 'url' => '/deleteTileServer', 'verb' => 'POST'],
		['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
		['name' => 'utils#saveOptionValue', 'url' => '/saveOptionValue', 'verb' => 'PUT'],
		['name' => 'utils#saveOptionValues', 'url' => '/saveOptionValues', 'verb' => 'PUT'],
		['name' => 'utils#moveTracks', 'url' => '/moveTracks', 'verb' => 'POST'],
		['name' => 'utils#cleanDb', 'url' => '/cleanDb', 'verb' => 'POST'],
	]
];
