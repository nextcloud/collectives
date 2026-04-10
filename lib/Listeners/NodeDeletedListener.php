<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\PageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;

/** @template-implements IEventListener<Event|NodeDeletedEvent> */
class NodeDeletedListener implements IEventListener {
	public function __construct(
		private PageMapper $pageMapper,
		private PageService $pageService,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof NodeDeletedEvent)) {
			return;
		}

		if ($this->pageService->isFromCollectives()) {
			return;
		}

		$node = $event->getNode();
		$collectiveId = NodeHelper::getCollectiveIdFromNode($node);
		if ($collectiveId === null) {
			return;
		}

		$this->pageMapper->trashByFileId($node->getId());
	}
}
