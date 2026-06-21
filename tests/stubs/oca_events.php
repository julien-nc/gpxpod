<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2015 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Sharing\Event {

	use OCP\EventDispatcher\Event;

	class BeforeTemplateRenderedEvent extends Event {
		public function getShare(): IShare {
		}
		public function getScope(): ?string {
		}
	}
}

namespace OCA\Files\Event {

	use OCP\EventDispatcher\Event;

	class LoadAdditionalScriptsEvent extends Event {
	}
}
