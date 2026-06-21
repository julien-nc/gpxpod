<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$appId = OCA\Gpxpod\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-vueGpxComparison');
