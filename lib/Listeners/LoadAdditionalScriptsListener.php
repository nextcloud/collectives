<?php

declare(strict_types=1);


namespace OCA\Collectives\Listeners;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class LoadAdditionalScriptsListener implements IEventListener {
	public function handle(Event $event): void {
		\OCP\Util::addScript('collectives', 'collectives-files');
	}
}
