<?php

declare(strict_types=1);


namespace OCA\Collectives\Listeners;

use OCA\Collectives\Events\CollectiveDeletedEvent;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Share\CollectiveShareService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class CollectiveDeletedListener implements IEventListener {
	/** @var CollectiveShareService */
	private $shareService;

	public function __construct(CollectiveShareService $shareService) {
		$this->shareService = $shareService;
	}

	/**
	 * @param Event $event
	 *
	 * @throws NotPermittedException
	 */
	public function handle(Event $event): void {
		if (!($event instanceOf CollectiveDeletedEvent)) {
			return;
		}

		// Delete any associated collective shares
		try {
			$this->shareService->deleteShareByCollectiveId($event->getCollective()->getId());
		} catch (NotFoundException $e) {
		}
	}
}
