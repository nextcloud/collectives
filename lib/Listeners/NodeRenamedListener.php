<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Mount\CollectiveStorage;
use OCA\Collectives\Service\PageService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<Event|NodeRenamedEvent> */
class NodeRenamedListener implements IEventListener {
	public function __construct(
		private readonly PageService $pageService,
		private readonly PageMapper $pageMapper,
		private readonly LoggerInterface $logger,
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
		$source = $event->getSource();

		if ($target instanceof Folder) {
			$this->handleFolderRenamed($source, $target);
			return;
		}

		if ($target instanceof File) {
			$this->handleFileRenamed($source, $target);
		}
	}

	private function handleFolderRenamed(Node $source, Folder $target): void {
		$sourceCollectiveId = ($source instanceof Folder) ? $this->getCollectiveIdFromFolder($source) : null;
		$targetCollectiveId = $this->getCollectiveIdFromFolder($target);

		if ($targetCollectiveId === null) {
			// Folder moved out of collective - trash all pages
			$this->trashFolderPages($target);
			return;
		}

		// Folder moved into or between collectives - process all files recursively
		$this->processFilesInFolder($target, $targetCollectiveId, $sourceCollectiveId);
	}

	private function handleFileRenamed(Node $source, File $target): void {
		$targetCollectiveId = NodeHelper::getCollectiveIdFromNode($target);

		if ($targetCollectiveId === null) {
			// File moved out of collective - trash the page
			$this->pageMapper->trashByFileId($target->getId());
			return;
		}

		// File moved into a collective or between collectives - update collectiveId
		$title = null;
		if ($source->getName() !== $target->getName()) {
			$title = NodeHelper::getTitleFromFile($target);
		}
		$userId = $target->getOwner()?->getUID();
		$this->pageService->updatePage($targetCollectiveId, $target->getId(), $userId, title: $title);
	}

	private function getCollectiveIdFromFolder(Folder $folder): ?int {
		// Check if folder belongs to a CollectiveStorage
		$storage = $folder->getStorage();
		if ($storage->instanceOfStorage(CollectiveStorage::class)) {
			/** @var CollectiveStorage $storage */
			return $storage->getFolderId();
		}

		// Fallback: Try to get collective ID from folder's internal path
		$internalPath = $folder->getInternalPath();
		if ($internalPath !== null) {
			return NodeHelper::extractCollectiveIdFromPath($internalPath);
		}

		return null;
	}

	private function trashFolderPages(Folder $folder): void {
		try {
			$nodes = $folder->getDirectoryListing();
			foreach ($nodes as $node) {
				if ($node instanceof File && NodeHelper::isPage($node)) {
					$this->pageMapper->trashByFileId($node->getId());
				} elseif ($node instanceof Folder) {
					$this->trashFolderPages($node);
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Collectives App Error: ' . $e->getMessage(),
				['exception' => $e]
			);
		}
	}

	private function processFilesInFolder(Folder $folder, int $targetCollectiveId, ?int $sourceCollectiveId): void {
		try {
			$nodes = $folder->getDirectoryListing();
			foreach ($nodes as $node) {
				if (str_starts_with($node->getName(), '.')) {
					// Skip hidden files/folders
					continue;
				}

				if ($node instanceof File && NodeHelper::isPage($node)) {
					// Update or create page for this file
					$userId = $node->getOwner()?->getUID();
					$title = NodeHelper::getTitleFromFile($node);
					$this->pageService->updatePage($targetCollectiveId, $node->getId(), $userId, title: $title);
				} elseif ($node instanceof Folder) {
					// Recursively process subfolders
					$this->processFilesInFolder($node, $targetCollectiveId, $sourceCollectiveId);
				}
			}
		} catch (\Exception $e) {
			$this->logger->error('Collectives App Error: ' . $e->getMessage(),
				['exception' => $e]
			);
		}
	}
}
