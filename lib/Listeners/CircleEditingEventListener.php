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
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use Symfony\Component\String\Slugger\SluggerInterface;

/** @template-implements IEventListener<Event|EditingCircleEvent> */
class CircleEditingEventListener implements IEventListener {
	public function __construct(
		private CollectiveMapper $collectiveMapper,
		private SluggerInterface $slugger,
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
		$slug = $this->slugger->slug($name)->toString();
		$collective->setSlug($slug);
		$this->collectiveMapper->update($collective);
	}
}
