<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\File;
use Symfony\Component\String\Slugger\SluggerInterface;

/** @template-implements IEventListener<Event|NodeRenamedEvent> */
class NodeRenamedListener implements IEventListener {
	public function __construct(
		private PageMapper $pageMapper,
		private SluggerInterface $slugger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof NodeRenamedEvent)) {
			return;
		}
		$source = $event->getSource();
		$target = $event->getTarget();

		if (!($source instanceof File && $target instanceof File)) {
			return;
		}

		$page = $this->pageMapper->findByFileId($target->getId());
		if ($page === null) {
			return;
		}

		$title = NodeHelper::getTitleFromFile($target);
		$page->setSlug($this->slugger->slug($title)->toString());
		$this->pageMapper->update($page);
	}
}
