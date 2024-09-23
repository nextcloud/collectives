<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\NotFoundException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;

/** @template-implements IEventListener<Event|CircleDestroyedEvent> */
class CircleDestroyedListener implements IEventListener {
	public function __construct(
		private CollectiveMapper $collectiveMapper,
		private CollectiveFolderManager $collectiveFolderManager,
	) {
	}

	/**
	 * @throws FilesNotPermittedException
	 */
	public function handle(Event $event): void {
		if (!($event instanceof CircleDestroyedEvent)) {
			return;
		}

		$collective = null;
		try {
			$collective = $this->collectiveMapper->findByCircleId($event->getCircle()->getSingleId());
		} catch (NotFoundException) {
		}

		if (!$collective) {
			return;
		}

		// Try to find and delete collective folder
		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException|FilesNotFoundException) {
		}

		$this->collectiveMapper->delete($collective);
	}
}
