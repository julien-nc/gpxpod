<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\GpxPod\Listener;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\GpxPod\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @implements IEventListener<Event>
 */
class AddFilesScriptsListener implements IEventListener {

	public function __construct(
		private IAppManager $appManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof LoadAdditionalScriptsEvent) {
			return;
		}

		if ($this->appManager->isEnabledForUser(Application::APP_ID)) {
			Util::addScript(Application::APP_ID, Application::APP_ID . '-filesPlugin', 'files');
		}
	}
}
