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
	   ['name' => 'page#gpxvcomp', 'url' => '/gpxvcomp', 'verb' => 'GET'],
	   ['name' => 'page#gpxvcompp', 'url' => '/gpxvcompp', 'verb' => 'POST'],
	   ['name' => 'page#do_echo', 'url' => '/echo', 'verb' => 'POST'],
	   ['name' => 'page#getgeo', 'url' => '/getgeo', 'verb' => 'POST'],
	   ['name' => 'page#getgeocol', 'url' => '/getgeocol', 'verb' => 'POST'],
	   ['name' => 'page#getmarkers', 'url' => '/getmarkers', 'verb' => 'POST'],
	   ['name' => 'page#killpython', 'url' => '/killpython', 'verb' => 'POST'],
	   ['name' => 'page#publink', 'url' => '/publink', 'verb' => 'GET'],
    ]
];
