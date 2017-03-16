<?php
/**
 * ownCloud - gpxpod
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@gmx.fr>
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
        ['name' => 'page#getgpx', 'url' => '/getgpx', 'verb' => 'POST'],
        ['name' => 'page#getpublicgpx', 'url' => '/getpublicgpx', 'verb' => 'POST'],
        ['name' => 'page#getmarkers', 'url' => '/getmarkers', 'verb' => 'POST'],
        ['name' => 'page#processTrackElevations', 'url' => '/processTrackElevations', 'verb' => 'POST'],
        ['name' => 'page#publink', 'url' => '/publink', 'verb' => 'GET'],
        ['name' => 'page#publicFile', 'url' => '/publicFile', 'verb' => 'GET'],
        ['name' => 'page#pubdirlink', 'url' => '/pubdirlink', 'verb' => 'GET'],
        ['name' => 'page#publicFolder', 'url' => '/publicFolder', 'verb' => 'GET'],
        ['name' => 'page#isFileShareable', 'url' => '/isFileShareable', 'verb' => 'POST'],
        ['name' => 'page#isFolderShareable', 'url' => '/isFolderShareable', 'verb' => 'POST'],
        ['name' => 'comparison#gpxvcomp', 'url' => '/gpxvcomp', 'verb' => 'GET'],
        ['name' => 'comparison#gpxvcompp', 'url' => '/gpxvcompp', 'verb' => 'POST'],
        ['name' => 'utils#cleanMarkersAndGeojsons', 'url' => '/cleanMarkersAndGeojsons', 'verb' => 'POST'],
        ['name' => 'utils#addTileServer', 'url' => '/addTileServer', 'verb' => 'POST'],
        ['name' => 'utils#deleteTileServer', 'url' => '/deleteTileServer', 'verb' => 'POST'],
        ['name' => 'utils#getOptionsValues', 'url' => '/getOptionsValues', 'verb' => 'POST'],
        ['name' => 'utils#saveOptionsValues', 'url' => '/saveOptionsValues', 'verb' => 'POST'],
    ]
];
