<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

//use OCA\GpxPod\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Server;

//\OC::$server->get(IAppManager::class)->loadApp(Application::APP_ID);
Server::get(IAppManager::class)->loadApps();
OC_Hook::clear();
