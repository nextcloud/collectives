<?php

namespace OCA\Collectives\Listeners;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class UnifiedSearchCSSLoader implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent) {
			return;
		}

		if ($event->isLoggedIn()) {
			Util::addStyle('collectives', 'unified-search');
		}
	}
}
