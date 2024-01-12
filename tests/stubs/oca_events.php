<?php

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
