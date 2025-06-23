<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Db\CollectiveShareMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Share\Events\ShareDeletedEvent;

/** @template-implements IEventListener<Event|ShareDeletedEvent> */
class ShareDeletedListener implements IEventListener {
	public function __construct(
		private CollectiveShareMapper $shareMapper,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof ShareDeletedEvent)
			|| !$event->getShare()->getToken()) {
			return;
		}

		// Delete any associated collective shares
		$this->shareMapper->deleteByToken($event->getShare()->getToken());
	}
}
