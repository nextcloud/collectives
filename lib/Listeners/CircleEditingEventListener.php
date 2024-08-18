<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Circles\Events\EditingCircleEvent;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\SlugService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\NotPermittedException as FilesNotPermittedException;

/** @template-implements IEventListener<Event|EditingCircleEvent> */
class CircleEditingEventListener implements IEventListener {
	public function __construct(
		private CollectiveMapper $collectiveMapper,
		private SlugService $slugService,
	) {
	}
	/**
	 * @throws FilesNotPermittedException
	 */
	public function handle(Event $event): void {
		if (!($event instanceof EditingCircleEvent)) {
			return;
		}

		try {
			$collective = $this->collectiveMapper->findByCircleId($event->getCircle()->getSingleId());
		} catch (NotFoundException) {
			return;
		}

		if (!$collective) {
			return;
		}

		$name = $event->getFederatedEvent()->getParams()->g('name');
		if (!$name) {
			return;
		}

		// Update slug if name has changed
		$slug = $this->slugService->generateCollectiveSlug($collective->getId(), $name);
		$collective->setSlug($slug);
		$this->collectiveMapper->update($collective);
	}
}
