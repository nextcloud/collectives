<?php

declare(strict_types=1);


namespace OCA\Collectives\Listeners;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/** @template-implements IEventListener<Event|BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		if ($event->isLoggedIn()) {
			Util::addStyle('collectives', 'collectives');
		}

		Util::addScript('collectives', 'collectives-files');
	}
}
