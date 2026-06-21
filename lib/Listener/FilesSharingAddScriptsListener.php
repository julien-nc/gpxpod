<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\GpxPod\Listener;

use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\GpxPod\AppInfo\Application;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @implements IEventListener<BeforeTemplateRenderedEvent>
 */
class FilesSharingAddScriptsListener implements IEventListener {

	public function __construct(
		private IInitialState $initialStateService,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		$state = [
			'sharingToken' => $event->getShare()->getToken(),
		];
		$this->initialStateService->provideInitialState('gpxpod-files', $state);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-filesPlugin');
	}
}
