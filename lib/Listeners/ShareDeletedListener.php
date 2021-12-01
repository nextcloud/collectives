<?php

declare(strict_types=1);


namespace OCA\Collectives\Listeners;

use OCA\Collectives\Db\CollectiveShareMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Share\Events\ShareDeletedEvent;

class ShareDeletedListener implements IEventListener {
	/** @var CollectiveShareMapper */
	private $shareMapper;

	public function __construct(CollectiveShareMapper $shareMapper) {
		$this->shareMapper = $shareMapper;
	}

	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!($event instanceOf ShareDeletedEvent) ||
			!$event->getShare()->getToken()) {
			return;
		}

		// Delete any associated collective shares
		$this->shareMapper->deleteByToken($event->getShare()->getToken());
	}
}
