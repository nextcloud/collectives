<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<Event|CircleDestroyedEvent> */
class CircleDestroyedListener implements IEventListener {
	public function __construct(
		private readonly CollectiveMapper $collectiveMapper,
		private readonly CollectiveService $collectiveService,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof CircleDestroyedEvent)) {
			return;
		}

		$collective = null;
		try {
			$collective = $this->collectiveMapper->findByCircleId($event->getCircle()->getSingleId(), true);
		} catch (NotFoundException) {
		}

		if (!$collective) {
			return;
		}

		try {
			$this->collectiveService->purgeCollective($collective);
		} catch (NotFoundException|NotPermittedException) {
			// Leftovers get picked up by the PurgeOrphanedCollectives background job
		}
	}
}
