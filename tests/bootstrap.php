<?php

require_once __DIR__ . '/../../../tests/bootstrap.php';

use OCA\GpxPod\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Server;

Server::get(IAppManager::class)->loadApp(Application::APP_ID);
OC_Hook::clear();
