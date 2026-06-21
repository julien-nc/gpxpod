<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once __DIR__ . '/../../../tests/bootstrap.php';

use OCA\GpxPod\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\Server;

Server::get(IAppManager::class)->loadApp(Application::APP_ID);
OC_Hook::clear();
