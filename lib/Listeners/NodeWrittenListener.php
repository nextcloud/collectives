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
use OCA\Collectives\Mount\CollectiveStorage;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\IConfig;

/** @template-implements IEventListener<Event|NodeWrittenEvent> */
class NodeWrittenListener implements IEventListener {
	public function __construct(
		private IConfig $config,
		private PageLinkMapper $pageLinkMapper,
		private CollectiveMapper $collectiveMapper,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof NodeWrittenEvent)) {
			return;
		}
		$node = $event->getNode();
		$storage = $node->getStorage();
		if (!($node instanceof File)
			|| $node->getMimeType() !== 'text/markdown'
			|| !$node->getStorage()->instanceOfStorage(CollectiveStorage::class)) {
			return;
		}

		/** @var CollectiveStorage $storage */
		$collectiveId = $storage->getFolderId();
		$collective = $this->collectiveMapper->idToCollective($collectiveId);

		$linkedPageIds = MarkdownHelper::getLinkedPageIds($collective, $node->getContent(), $this->config->getSystemValue('trusted_domains', []));
		$this->pageLinkMapper->updateByPageId($node->getId(), $linkedPageIds);
	}
}
