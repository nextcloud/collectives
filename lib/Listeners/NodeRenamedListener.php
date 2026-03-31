<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\PageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;

/** @template-implements IEventListener<Event|NodeRenamedEvent> */
class NodeRenamedListener implements IEventListener {
	public function __construct(
		private readonly PageService $pageService,
		private readonly PageMapper $pageMapper,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof NodeRenamedEvent)) {
			return;
		}

		if ($this->pageService->isFromCollectives()) {
			return;
		}

		$target = $event->getTarget();
		$targetCollectiveId = NodeHelper::getCollectiveIdFromNode($target);
		if ($targetCollectiveId === null) {
			$this->pageMapper->trashByFileId($target->getId());
			return;
		}

		// File moved into a collective or between collectives - update collectiveId
		$source = $event->getSource();
		$title = null;
		if ($source->getName() !== $target->getName()) {
			$title = NodeHelper::getTitleFromFile($target);
		}
		$this->pageService->updatePage($targetCollectiveId, $target->getId(), null, null, null, $title);
	}
}
