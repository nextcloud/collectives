<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageLinkMapper;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Model\CollectiveFileInfo;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\DB\Exception as DBException;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IUserManager;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Builds the PageInfo tree for a single folder of a collective.
 *
 * One instance is created per call (see PageInfoTreeBuilderFactory). The filecache
 * lookup, batched metadata queries and other values that only depend on the
 * constructor arguments are computed lazily and cached for the lifetime of the
 * instance.
 */
class PageInfoTreeBuilder {
	/** @var CollectiveFileInfo[]|null */
	private ?array $fileInfos = null;
	/** @var array<int, CollectiveFileInfo[]>|null */
	private ?array $childrenByParent = null;
	/** @var int[]|null */
	private ?array $pageFileIds = null;
	/** @var array<int, Page>|null */
	private ?array $pagesByFileId = null;
	/** @var array<int, int[]>|null */
	private ?array $linkedPageIdsByFileId = null;
	/** @var array<string, null|string>|null */
	private ?array $displayNames = null;
	private ?string $collectivePath = null;

	public function __construct(
		private readonly PageMapper $pageMapper,
		private readonly PageLinkMapper $pageLinkMapper,
		private readonly IUserManager $userManager,
		private readonly SluggerInterface $slugger,
		private readonly CollectiveFolderManager $collectiveFolderManager,
		private readonly CollectiveServiceBase $collectiveService,
		private readonly int $collectiveId,
		private readonly Folder $folder,
		private readonly string $userId,
		private readonly bool $recurse,
		private readonly bool $forceIndex,
	) {
	}

	/**
	 * Recursively build PageInfo objects from the in-memory filecache tree.
	 *
	 * @param PageInfo[] $pageInfos
	 *
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function build(int $folderId, int $parentPageId, array &$pageInfos): void {
		$indexFileInfo = null;
		$pageFileInfos = [];
		$subFolderIds = [];
		foreach ($this->childrenByParent()[$folderId] ?? [] as $child) {
			if (str_starts_with($child->name, '.')) {
				// Ignore hidden folders and files
				continue;
			}

			if (isset($this->childrenByParent()[$child->fileId])) {
				// Has children, so it's a (non-empty) folder
				$subFolderIds[] = $child->fileId;
			} elseif ($child->isIndexPage()) {
				$indexFileInfo = $child;
			} elseif ($child->isPage()) {
				$pageFileInfos[] = $child;
			}
		}

		// forceIndex only applies to the entry folder, not to subfolders
		$forceIndex = $this->forceIndex && $folderId === $this->folder->getId();

		if ($indexFileInfo === null) {
			if (!$forceIndex && !$this->folderHasPages($folderId)) {
				// Ignore folders without an index page and without any (sub)pages
				return;
			}

			// Create missing index page if folder or subfolders have page files (or forceIndex)
			$folder = $this->folder->getFirstNodeById($folderId);
			if (!($folder instanceof Folder)) {
				return;
			}
			$indexPageInfo = $this->createIndexPage($folder, $parentPageId);
			$indexPageId = $indexPageInfo->getId();
		} else {
			$indexPageInfo = $this->buildPageInfo($indexFileInfo, $parentPageId);
			$indexPageId = $indexFileInfo->fileId;
		}
		$pageInfos[] = $indexPageInfo;

		foreach ($pageFileInfos as $pageFileInfo) {
			$pageInfos[] = $this->buildPageInfo($pageFileInfo, $indexPageId);
		}

		foreach ($subFolderIds as $subFolderId) {
			if ($this->recurse) {
				$this->build($subFolderId, $indexPageId, $pageInfos);
				continue;
			}

			// Not recursive: only add the subfolder's index page (ignore subfolders without one)
			$subIndexFileInfo = $this->findIndexPageInfo($subFolderId);
			if ($subIndexFileInfo !== null) {
				$pageInfos[] = $this->buildPageInfo($subIndexFileInfo, $indexPageId);
			}
		}
	}

	/**
	 * @return CollectiveFileInfo[]
	 *
	 * @throws NotFoundException
	 */
	private function fileInfos(): array {
		if ($this->fileInfos === null) {
			try {
				$this->fileInfos = $this->collectiveFolderManager->getFileCacheForCollective($this->collectiveId, $this->folder->getInternalPath());
			} catch (DBException $e) {
				throw new NotFoundException($e->getMessage(), 0, $e);
			}
		}

		return $this->fileInfos;
	}

	/**
	 * Group child entries by their parent folder file id.
	 *
	 * @return array<int, CollectiveFileInfo[]>
	 *
	 * @throws NotFoundException
	 */
	private function childrenByParent(): array {
		if ($this->childrenByParent === null) {
			$this->childrenByParent = [];
			foreach ($this->fileInfos() as $fileInfo) {
				$this->childrenByParent[$fileInfo->parent][] = $fileInfo;
			}
		}

		return $this->childrenByParent;
	}

	/**
	 * File ids of all page files in the tree.
	 *
	 * @return int[]
	 *
	 * @throws NotFoundException
	 */
	private function pageFileIds(): array {
		if ($this->pageFileIds === null) {
			$this->pageFileIds = [];
			foreach ($this->fileInfos() as $fileInfo) {
				if ($fileInfo->isPage()) {
					$this->pageFileIds[] = $fileInfo->fileId;
				}
			}
		}

		return $this->pageFileIds;
	}

	/**
	 * Batch load page metadata for all page files.
	 *
	 * @return array<int, Page>
	 *
	 * @throws NotFoundException
	 */
	private function pagesByFileId(): array {
		if ($this->pagesByFileId === null) {
			$this->pagesByFileId = $this->pageMapper->findByFileIds($this->pageFileIds());
		}

		return $this->pagesByFileId;
	}

	/**
	 * @return array<int, int[]>
	 *
	 * @throws NotFoundException
	 */
	private function linkedPageIdsByFileId(): array {
		if ($this->linkedPageIdsByFileId === null) {
			$this->linkedPageIdsByFileId = $this->pageLinkMapper->findByPageIds($this->pageFileIds());
		}

		return $this->linkedPageIdsByFileId;
	}

	/**
	 * @return array<string, null|string>
	 *
	 * @throws NotFoundException
	 */
	private function displayNames(): array {
		if ($this->displayNames === null) {
			$this->displayNames = [];
			foreach ($this->pagesByFileId() as $page) {
				$lastUserId = $page->getLastUserId();
				if ($lastUserId !== null && !isset($this->displayNames[$lastUserId])) {
					$this->displayNames[$lastUserId] = $this->userManager->getDisplayName($lastUserId);
				}
			}
		}

		return $this->displayNames;
	}

	/**
	 * Derive collectivePath from the mount point (incl. user folder prefix),
	 * matching PageInfo::fromFile().
	 *
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function collectivePath(): string {
		if ($this->collectivePath === null) {
			$mountPoint = explode('/', $this->folder->getMountPoint()->getMountPoint(), 4);
			$this->collectivePath = (count($mountPoint) >= 4)
				? rtrim($mountPoint[3], '/')
				: $this->collectiveService->getCollective($this->collectiveId, $this->userId)->getName();
		}

		return $this->collectivePath;
	}

	/**
	 * @throws NotFoundException
	 */
	private function findIndexPageInfo(int $folderId): ?CollectiveFileInfo {
		foreach ($this->childrenByParent()[$folderId] ?? [] as $child) {
			if ($child->isIndexPage()) {
				return $child;
			}
		}

		return null;
	}

	/**
	 * @throws NotFoundException
	 */
	private function folderHasPages(int $folderId): bool {
		foreach ($this->childrenByParent()[$folderId] ?? [] as $child) {
			if (str_starts_with($child->name, '.')) {
				continue;
			}

			if (isset($this->childrenByParent()[$child->fileId])) {
				if ($this->folderHasPages($child->fileId)) {
					return true;
				}
			} elseif ($child->isPage()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function buildPageInfo(CollectiveFileInfo $fileInfo, int $parentId): PageInfo {
		$page = $this->pagesByFileId()[$fileInfo->fileId] ?? null;
		$lastUserId = $page?->getLastUserId();
		$pageInfo = new PageInfo();
		$pageInfo->fromFileInfo(
			$fileInfo,
			$parentId,
			$this->collectivePath(),
			$lastUserId,
			$lastUserId !== null ? ($this->displayNames()[$lastUserId] ?? null) : null,
			$page?->getEmoji(),
			$page?->getSubpageOrder(),
			$page !== null && $page->getFullWidth(),
			$page?->getSlug(),
			$page?->getTags(),
			$this->linkedPageIdsByFileId()[$fileInfo->fileId] ?? null,
		);

		return $pageInfo;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function createIndexPage(Folder $folder, int $parentPageId): PageInfo {
		try {
			$newFile = $folder->newFile(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		$pageInfo = new PageInfo();
		try {
			$pageInfo->fromFile(
				$newFile,
				$parentPageId,
				$this->userId,
				$this->userManager->getDisplayName($this->userId),
			);
			$slug = $this->slugger->slug(PageInfo::INDEX_PAGE_TITLE)->toString();
			$page = new Page();
			$page->setFileId($newFile->getId());
			$page->setLastUserId($this->userId);
			$page->setSlug($slug);
			$this->pageMapper->updateOrInsert($page);
			$pageInfo->setSlug($slug);
		} catch (FilesNotFoundException|InvalidPathException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		return $pageInfo;
	}
}
