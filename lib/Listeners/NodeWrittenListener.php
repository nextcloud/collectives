<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\PageLinkMapper;
use OCA\Collectives\Fs\MarkdownHelper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\PageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Node;
use OCP\IConfig;

/** @template-implements IEventListener<Event|NodeWrittenEvent> */
class NodeWrittenListener implements IEventListener {
	public function __construct(
		private IConfig $config,
		private PageLinkMapper $pageLinkMapper,
		private CollectiveMapper $collectiveMapper,
		private PageService $pageService,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof NodeWrittenEvent)) {
			return;
		}

		$node = $event->getNode();
		$collectiveId = NodeHelper::getCollectiveIdFromNode($node);
		if ($collectiveId === null) {
			return;
		}

		$fileId = $node->getId();
		$this->updatePageLinks($collectiveId, $node, $fileId);

		if ($this->pageService->isFromCollectives()) {
			return;
		}

		$this->updateCollectivePage($node, $collectiveId, $fileId);
	}

	private function updatePageLinks(int $collectiveId, Node $node, int $fileId): void {
		$collective = $this->collectiveMapper->idToCollective($collectiveId);
		$linkedPageIds = MarkdownHelper::getLinkedPageIds($collective, $node->getContent(), $this->config->getSystemValue('trusted_domains', []));
		$this->pageLinkMapper->updateByPageId($fileId, $linkedPageIds);
	}

	private function updateCollectivePage(Node $node, int $collectiveId, int $fileId): void {
		$title = NodeHelper::getTitleFromFile($node);
		$userId = $node->getOwner()->getUID();
		$this->pageService->updatePage($collectiveId, $fileId, $userId, null, null, $title);
	}
}
