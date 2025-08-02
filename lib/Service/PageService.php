<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use Exception;
use OC;
use OC_Util;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Db\TagMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Trash\PageTrashBackend;
use OCA\NotifyPush\Queue\IQueue;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use Psr\Container\ContainerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class PageService {
	private const DEFAULT_PAGE_TITLE = 'New Page';

	private ?IQueue $pushQueue = null;
	private ?Collective $collective = null;
	private ?PageTrashBackend $trashBackend = null;

	public function __construct(
		private IAppManager $appManager,
		private PageMapper $pageMapper,
		private NodeHelper $nodeHelper,
		private CollectiveServiceBase $collectiveService,
		private UserFolderHelper $userFolderHelper,
		private IUserManager $userManager,
		private IConfig $config,
		ContainerInterface $container,
		private SessionService $sessionService,
		private SluggerInterface $slugger,
		private TagMapper $tagMapper,
	) {
		try {
			$this->pushQueue = $container->get(IQueue::class);
		} catch (Exception) {
		}
	}

	private function initTrashBackend(): void {
		if ($this->appManager->isEnabledForUser('files_trashbin')) {
			$this->trashBackend = OC::$server->get(PageTrashBackend::class);
		}
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getCollective(int $collectiveId, string $userId): Collective {
		if ($this->collective === null || $this->collective->getId() !== $collectiveId) {
			$this->collective = $this->collectiveService->getCollective($collectiveId, $userId);
		}

		return $this->collective;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function verifyEditPermissions(int $collectiveId, string $userId): void {
		if (!$this->getCollective($collectiveId, $userId)->canEdit()) {
			throw new NotPermittedException('Not allowed to edit collective');
		}
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectiveFolder(int $collectiveId, string $userId): Folder {
		$collectiveName = $this->getCollective($collectiveId, $userId)->getName();
		try {
			$folder = $this->userFolderHelper->get($userId)->get($collectiveName);
		} catch (FilesNotFoundException) {
			// Workaround issue #332
			OC_Util::setupFS($userId);
			$folder = $this->userFolderHelper->get($userId)->get($collectiveName);
		}

		if (!($folder instanceof Folder)) {
			throw new FilesNotFoundException('Folder not found for collective ' . $collectiveId);
		}

		return $folder;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getPageFile(int $collectiveId, int $fileId, string $userId): File {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		return $this->nodeHelper->getFileById($collectiveFolder, $fileId);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getFolder(int $collectiveId, int $fileId, string $userId): Folder {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		if ($fileId === 0) {
			return $collectiveFolder;
		}

		$file = $this->nodeHelper->getFileById($collectiveFolder, $fileId);
		if (!($file->getParent() instanceof Folder)) {
			throw new NotFoundException('Error getting parent folder for file ' . $fileId . ' in collective ' . $collectiveId);
		}

		return $file->getParent();
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function isPageInPageFolder(int $collectiveId, int $parentId, int $pageId, string $userId): void {
		$folder = $this->getFolder($collectiveId, $parentId, $userId);
		if (!isset($folder->getById($pageId)[0])) {
			throw new NotFoundException('Page ' . $pageId . ' is not a child of ' . $parentId);
		}
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getSubpagesFromFile(int $collectiveId, File $file, string $userId): array {
		if (!NodeHelper::isIndexPage($file)) {
			return [];
		}

		$parentId = $file->getId();
		$folder = $this->getFolder($collectiveId, $parentId, $userId);
		return array_filter($this->getPagesFromFolder($collectiveId, $folder, $userId), static fn (PageInfo $pageInfo) => $pageInfo->getParentId() === $parentId);
	}

	/**
	 * @throws NotFoundException
	 */
	private function getParentPageId(File $file, ?Node $parent = null): int {
		try {
			if (NodeHelper::isLandingPage($file)) {
				// Return `0` for landing page
				return 0;
			}

			$parent ??= $file->getParent();

			if (NodeHelper::isIndexPage($file)) {
				// Go down two levels if index page but not landing page
				return self::getIndexPageFile($parent->getParent())->getId();
			}

			return self::getIndexPageFile($parent)->getId();
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws NotFoundException
	 */
	private function getPageByFile(File $file, ?Node $parent = null): PageInfo {
		try {
			$page = $this->pageMapper->findByFileId($file->getId());
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
		$lastUserId = ($page !== null) ? $page->getLastUserId() : null;
		$emoji = ($page !== null) ? $page->getEmoji() : null;
		$subpageOrder = ($page !== null) ? $page->getSubpageOrder() : null;
		$fullWidth = $page !== null && $page->getFullWidth();
		$slug = ($page !== null) ? $page->getSlug() : null;
		$tags = ($page !== null) ? $page->getTags() : null;
		$pageInfo = new PageInfo();
		try {
			$pageInfo->fromFile($file,
				$this->getParentPageId($file, $parent),
				$lastUserId,
				$lastUserId ? $this->userManager->getDisplayName($lastUserId) : null,
				$emoji,
				$subpageOrder,
				$fullWidth,
				$slug,
				$tags);
		} catch (FilesNotFoundException|InvalidPathException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		return $pageInfo;
	}

	/**
	 * @throws NotFoundException
	 */
	private function getTrashPageByFile(File $file, string $filename, string $timestamp): PageInfo {
		try {
			$page = $this->pageMapper->findByFileId($file->getId(), true);
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
		$lastUserId = ($page !== null) ? $page->getLastUserId() : null;
		$emoji = ($page !== null) ? $page->getEmoji() : null;
		$subpageOrder = ($page !== null) ? $page->getSubpageOrder() : null;
		$fullWidth = $page !== null && $page->getFullWidth();
		$slug = ($page !== null) ? $page->getSlug() : null;
		$tags = ($page !== null) ? $page->getTags() : null;
		$trashTimestamp = ($page !== null) ? $page->getTrashTimestamp(): (int)$timestamp;
		$pageInfo = new PageInfo();
		try {
			$pageInfo->fromFile($file,
				0,
				$lastUserId,
				$lastUserId ? $this->userManager->getDisplayName($lastUserId) : null,
				$emoji,
				$subpageOrder,
				$fullWidth,
				$slug,
				$tags);
			$pageInfo->setTrashTimestamp($trashTimestamp);
			$pageInfo->setFilePath('');
			$pageInfo->setTitle(basename($filename, PageInfo::SUFFIX));
		} catch (FilesNotFoundException|InvalidPathException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		return $pageInfo;
	}

	private function notifyPush(array $body): void {
		if (!$this->pushQueue) {
			return;
		}

		$sessionUsers = $this->sessionService->getSessionUsers($body['collectiveId']);
		foreach ($sessionUsers as $userId) {
			$notification = [
				'user' => $userId,
				'message' => 'collectives_pagelist',
				'body' => $body,
			];
			$this->pushQueue->push('notify_custom', $notification);
		}
	}

	private function updatePage(int $collectiveId, int $fileId, string $userId, ?string $emoji = null, ?bool $fullWidth = null, ?string $slug = null, ?string $tags = null): void {
		$page = new Page();
		$page->setFileId($fileId);
		$page->setLastUserId($userId);
		if ($emoji !== null) {
			$page->setEmoji($emoji);
		}
		if ($fullWidth !== null) {
			$page->setFullWidth($fullWidth);
		}
		if ($slug !== null) {
			$page->setSlug($slug);
		}
		if ($tags !== null) {
			$page->setTags($tags);
		}
		$this->pageMapper->updateOrInsert($page);
	}

	/**
	 * @throws NotFoundException
	 */
	private function updateSubpageOrder(int $collectiveId, int $fileId, string $userId, string $subpageOrder): void {
		$page = new Page();
		$page->setFileId($fileId);
		$page->setSubpageOrder($subpageOrder);
		if ($this->pageMapper->findByFileId($fileId) === null) {
			// Required if page metadata in DB not present yet
			$page->setLastUserId($userId);
		}
		$this->pageMapper->updateOrInsert($page);
	}

	private function updateTags(int $collectiveId, int $fileId, string $userId, string $tags): void {
		$page = new Page();
		$page->setFileId($fileId);
		$page->setTags($tags);
		if ($this->pageMapper->findByFileId($fileId) === null) {
			// Required if page metadata in DB not present yet
			$page->setLastUserId($userId);
		}
		$this->pageMapper->updateOrInsert($page);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function newPage(int $collectiveId, Folder $folder, string $filename, string $userId, ?string $title): PageInfo {
		try {
			$newFile = $folder->newFile($filename . PageInfo::SUFFIX);
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		$pageInfo = new PageInfo();
		try {
			$pageInfo->fromFile($newFile,
				$this->getParentPageId($newFile),
				$userId,
				$this->userManager->getDisplayName($userId));
			$slug = $title ? $this->slugger->slug($title)->toString() : null;
			$this->updatePage($collectiveId, $newFile->getId(), $userId, null, null, $slug);
			$pageInfo->setSlug($slug);
		} catch (FilesNotFoundException|InvalidPathException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		return $pageInfo;
	}

	/**
	 * @throws NotPermittedException
	 */
	public function initSubFolder(File $file): Folder {
		$folder = $file->getParent();
		if (NodeHelper::isIndexPage($file)) {
			return $folder;
		}

		try {
			$folderName = NodeHelper::generateFilename($folder, basename($file->getName(), PageInfo::SUFFIX));
			$subFolder = $folder->newFolder($folderName);
			$file->move($subFolder->getPath() . '/' . PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
		} catch (InvalidPathException|FilesNotFoundException|FilesNotPermittedException|LockedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
		return $subFolder;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws FilesNotFoundException
	 */
	public function pageToSubFolder(int $collectiveId, int $pageId, string $userId): PageInfo {
		$file = $this->getPageFile($collectiveId, $pageId, $userId);
		if (!NodeHelper::isIndexPage($file)) {
			$this->initSubFolder($file);
		}

		return $this->findByFileId($collectiveId, $pageId, $userId);
	}

	/**
	 * @throws NotFoundException
	 */
	public static function getIndexPageFile(Folder $folder): File {
		try {
			$file = $folder->get(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		if (!($file instanceof File)) {
			throw new NotFoundException('Failed to get index page');
		}

		return $file;
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getPagesFromFolder(int $collectiveId, Folder $folder, string $userId, bool $recurse = false, bool $forceIndex = false): array {
		$subPageInfos = [];
		$folderNodes = $folder->getDirectoryListing();

		$hasPages = false;
		$pageFiles = [];
		foreach ($folderNodes as $node) {
			if (str_starts_with($node->getName(), '.')) {
				// Ignore hidden folders
				continue;
			}

			if ($node instanceof Folder) {
				if ($recurse) {
					// Recursive: get subpage infos from folder
					try {
						array_push($subPageInfos, ...$this->getPagesFromFolder($collectiveId, $node, $userId, true));
					} catch (NotFoundException) {
						// If parent folder doesn't have an index page, `getPagesFromFolder()` throws NotFoundException even though having subpages.
						$hasPages = true;
					}
				} else {
					// Not recursive: get index page of folder, as the folder is not to be processed
					try {
						$subPageInfos[] = $this->getPageByFile(self::getIndexPageFile($node), $node);
					} catch (NotFoundException) {
						// Ignore subfolders without index page
					}
				}
			} elseif ($node instanceof File && NodeHelper::isPage($node)) {
				$hasPages = true;
				$pageFiles[] = $node;
				if (!isset($indexPage) && NodeHelper::isIndexPage($node)) {
					$indexPage = $this->getPageByFile($node, $folder);
				}
			}
		}

		// One of the subfolders had a page
		if (isset($subPageInfos[0])) {
			$hasPages = true;
		}

		if (!isset($indexPage)) {
			if ($hasPages || $forceIndex) {
				// Create missing index page if folder or subfolders have page files (or forceIndex)
				$indexPage = $this->newPage($collectiveId, $folder, PageInfo::INDEX_PAGE_TITLE, $userId, PageInfo::INDEX_PAGE_TITLE);
			} else {
				// Ignore folders without an index page
				return [];
			}
		}

		// Add markdown files from this folder
		$folderPageInfos = [];
		foreach ($pageFiles as $pageFile) {
			if (NodeHelper::isIndexPage($pageFile)) {
				continue;
			}

			try {
				$pageInfo = $this->getPageByFile($pageFile, $folder);
			} catch (NotFoundException) {
				// If parent folder doesn't have an index page, it throws NotFoundException.
				continue;
			}

			$folderPageInfos[] = $pageInfo;
		}

		return array_merge([$indexPage], $folderPageInfos, $subPageInfos);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findChildren(int $collectiveId, int $parentId, string $userId): array {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$parentFile = $this->nodeHelper->getFileById($collectiveFolder, $parentId);
		if (!NodeHelper::isIndexPage($parentFile)) {
			throw new NotFoundException('Not an index page: ' . $parentId);
		}

		$folder = $this->getFolder($collectiveId, $parentId, $userId);
		try {
			return $this->getPagesFromFolder($collectiveId, $folder, $userId, true);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findAll(int $collectiveId, string $userId): array {
		$folder = $this->getCollectiveFolder($collectiveId, $userId);
		try {
			return $this->getPagesFromFolder($collectiveId, $folder, $userId, true, true);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findAllTrash(int $collectiveId, string $userId): array {
		$this->verifyEditPermissions($collectiveId, $userId);

		$this->initTrashBackend();
		if (!$this->trashBackend) {
			return [];
		}

		$trashNodes = $this->trashBackend->listTrashForCollective($this->userManager->get($userId), $collectiveId);
		$trashPageInfos = [];
		foreach ($trashNodes as $node) {
			$pathParts = pathInfo($node->getName());
			$filename = $pathParts['filename'];
			$timestamp = ltrim($pathParts['extension'], 'd');

			if ($node instanceof Folder) {
				try {
					$node = $node->get(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
				} catch (FilesNotFoundException) {
					// Ignore folders without index page
					continue;
				}
			}

			// Ignore everything except files
			if (!($node instanceof File)) {
				continue;
			}

			$trashPageInfos[] = $this->getTrashPageByFile($node, $filename, $timestamp);
		}

		return $trashPageInfos;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findByString(int $collectiveId, string $search, string $userId): array {
		$allPageInfos = $this->findAll($collectiveId, $userId);
		$pageInfos = [];
		foreach ($allPageInfos as $pageInfo) {
			if (stripos($pageInfo->getTitle(), $search) === false) {
				continue;
			}
			$pageInfos[] = $pageInfo;
		}

		return $pageInfos;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findByPath(int $collectiveId, string $path, string $userId, ?int $parentId = null): PageInfo {
		$collectiveFolder = $parentId
			? $this->getFolder($collectiveId, $parentId, $userId)
			: $this->getCollectiveFolder($collectiveId, $userId);
		$landingPageId = self::getIndexPageFile($collectiveFolder)->getId();

		if ($path === '' || $path === '/') {
			// Return landing page
			return $this->findByFileId($collectiveId, $landingPageId, $userId);
		}

		$parentPageId = $landingPageId;
		$allPages = $this->findAll($collectiveId, $userId);
		foreach (explode('/', $path) as $title) {
			$matchingPage = null;
			foreach ($allPages as $pageInfo) {
				if ($pageInfo->getTitle() === $title && $pageInfo->getParentId() === $parentPageId) {
					$matchingPage = $pageInfo;
					break;
				}
			}
			if (!$matchingPage) {
				throw new NotFoundException('Failed to get page by path ' . $path . ' in collective ' . $collectiveId);
			}
			$parentPageId = $matchingPage->getId();
		}

		return $matchingPage;
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findByFileId(int $collectiveId, int $fileId, string $userId, ?int $parentId = null): PageInfo {
		$collectiveFolder = $parentId
			? $this->getFolder($collectiveId, $parentId, $userId)
			: $this->getCollectiveFolder($collectiveId, $userId);
		$pageFile = $collectiveFolder->getById($fileId);
		if (isset($pageFile[0]) && $pageFile[0] instanceof File) {
			$pageFile = $pageFile[0];
			return $this->findByFile($collectiveId, $pageFile, $userId);
		}
		throw new NotFoundException('Failed to get page by file ID ' . $fileId . ' in collective ' . $collectiveId);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function find(int $collectiveId, int $id, string $userId): PageInfo {
		$folder = $this->getCollectiveFolder($collectiveId, $userId);
		return $this->getPageByFile($this->nodeHelper->getFileById($folder, $id));
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findByFile(int $collectiveId, File $file, string $userId): PageInfo {
		try {
			return $this->find($collectiveId, $file->getId(), $userId);
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function create(int $collectiveId, int $parentId, string $title, ?int $templateId, string $userId, ?string $defaultTitle = null): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$folder = $this->getFolder($collectiveId, $parentId, $userId);
		$parentFile = $this->nodeHelper->getFileById($folder, $parentId);
		$folder = $this->initSubFolder($parentFile);
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, $defaultTitle ?: self::DEFAULT_PAGE_TITLE);
		$filename = NodeHelper::generateFilename($folder, $safeTitle, PageInfo::SUFFIX);

		$pageInfo = $templateId
			? $this->copy($collectiveId, $templateId, $parentId, $safeTitle, 0, $userId)
			: $this->newPage($collectiveId, $folder, $filename, $userId, $title);
		$parentPageInfo = $this->addToSubpageOrder($collectiveId, $parentId, $pageInfo->getId(), 0, $userId);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo, $parentPageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function touch(int $collectiveId, int $id, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$folder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageInfo = $this->getPageByFile($file);
		$pageInfo->setLastUserId($userId);
		$pageInfo->setLastUserDisplayName($this->userManager->getDisplayName($userId));
		$this->updatePage($collectiveId, $pageInfo->getId(), $userId);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setFullWidth(int $collectiveId, int $id, string $userId, bool $fullWidth): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$folder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageInfo = $this->getPageByFile($file);
		$pageInfo->setLastUserId($userId);
		$pageInfo->setLastUserDisplayName($this->userManager->getDisplayName($userId));
		$pageInfo->setFullWidth($fullWidth);
		$this->updatePage($collectiveId, $pageInfo->getId(), $userId, fullWidth: $fullWidth);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws NotFoundException
	 */
	private function isAncestorOf(Folder $collectiveFolder, int $pageId, int $targetId): bool {
		$targetFile = $this->nodeHelper->getFileById($collectiveFolder, $targetId);
		if (NodeHelper::isLandingPage($targetFile)) {
			return false;
		}

		$targetParentPageId = $this->getParentPageId($targetFile);
		if ($pageId === $targetParentPageId) {
			return true;
		}

		return $this->isAncestorOf($collectiveFolder, $pageId, $targetParentPageId);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function moveOrCopyPage(Folder $collectiveFolder, File $file, int $parentId, ?string $title, bool $copy, ?Folder $newCollectiveFolder = null): ?File {
		$targetFolder = $newCollectiveFolder ?: $collectiveFolder;

		// Do not allow to move/copy the landing page
		if (NodeHelper::isLandingPage($file)) {
			throw new NotPermittedException('Not allowed to move or copy landing page');
		}

		// Do not allow to move/copy a page to itself
		try {
			if ($parentId === $file->getId()) {
				throw new NotPermittedException('Not allowed to move or copy a page to itself');
			}
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		// Do not allow to move or copy a page to a subpage of itself
		if ($this->isAncestorOf($targetFolder, $file->getId(), $parentId)) {
			throw new NotPermittedException('Not allowed to move or copy a page to a subpage of itself');
		}

		$toNewFolder = false;
		if ($parentId !== $this->getParentPageId($file)) {
			$newFolder = $this->initSubFolder($this->nodeHelper->getFileById($targetFolder, $parentId));
			$toNewFolder = true;
		} else {
			$newFolder = $this->nodeHelper->getFileById($targetFolder, $parentId)->getParent();
		}

		// If processing an index page, then move/copy the parent folder, otherwise the file itself
		$node = NodeHelper::isIndexPage($file) ? $file->getParent() : $file;
		$suffix = NodeHelper::isIndexPage($file) ? '' : PageInfo::SUFFIX;
		if (!$title) {
			$title = basename($node->getName(), $suffix);
		}
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		$newSafeName = $safeTitle . $suffix;
		$newFileName = NodeHelper::generateFilename($newFolder, $safeTitle, PageInfo::SUFFIX);

		// Not copying and neither path nor title changed, nothing to do
		if (!$copy && !$toNewFolder && $newSafeName === $node->getName()) {
			return null;
		}

		try {
			if ($copy) {
				$newNode = $node->copy($newFolder->getPath() . '/' . $newFileName . $suffix);
			} else {
				$newNode = $node->move($newFolder->getPath() . '/' . $newFileName . $suffix);
			}
		} catch (InvalidPathException|FilesNotFoundException|LockedException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		if ($newNode instanceof Folder) {
			// Return index page if node is a folder
			$newNode = self::getIndexPageFile($newNode);
		} elseif (!($newNode instanceof File)) {
			throw new NotFoundException('Node not a file: ' . $node->getId());
		}
		return $newNode;
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function copy(int $collectiveId, int $id, ?int $parentId, ?string $title, int $index, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $id);
		$pageInfo = $this->getPageByFile($file);
		$oldParentId = $this->getParentPageId($file);
		$parentId = $parentId ?: $oldParentId;

		if (null !== $newFile = $this->moveOrCopyPage($collectiveFolder, $file, $parentId, $title, true)) {
			$file = $newFile;
		}
		$slug = $this->slugger->slug($title ?: $pageInfo->getTitle())->toString();
		try {
			$this->updatePage($collectiveId, $file->getId(), $userId, $pageInfo->getEmoji(), $pageInfo->isFullWidth(), $slug, $pageInfo->getTags());
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		$parentPageInfo = $this->addToSubpageOrder($collectiveId, $parentId, $file->getId(), $index, $userId);
		$pageInfo = $this->getPageByFile($file);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo, $parentPageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function move(int $collectiveId, int $id, ?int $parentId, ?string $title, int $index, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $id);
		$oldParentId = $this->getParentPageId($file);
		$parentId = $parentId ?: $oldParentId;

		if (null !== $newFile = $this->moveOrCopyPage($collectiveFolder, $file, $parentId, $title, false)) {
			$file = $newFile;
		}
		$slug = $title ? $this->slugger->slug($title)->toString() : null;

		try {
			$this->updatePage($collectiveId, $file->getId(), $userId, null, null, $slug);
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		$pageInfo = $this->getPageByFile($file);
		if ($oldParentId !== $parentId) {
			// Page got moved: remove from subpage order of old parent page, add to new
			$oldParentPageInfo = $this->removeFromSubpageOrder($collectiveId, $oldParentId, $id, $userId);
			$newParentPageInfo = $this->addToSubpageOrder($collectiveId, $parentId, $file->getId(), $index, $userId);
			$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo, $oldParentPageInfo, $newParentPageInfo]]);
		} else {
			$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		}

		return $this->getPageByFile($file);
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function copyToCollective(int $collectiveId, int $id, int $newCollectiveId, ?int $parentId, int $index, string $userId): void {
		$this->verifyEditPermissions($collectiveId, $userId);
		$this->verifyEditPermissions($newCollectiveId, $userId);
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$newCollectiveFolder = $this->getCollectiveFolder($newCollectiveId, $userId);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $id);
		$pageInfo = $this->getPageByFile($file);
		$parentId = $parentId ?: self::getIndexPageFile($newCollectiveFolder)->getId();

		if (null !== $newFile = $this->moveOrCopyPage($collectiveFolder, $file, $parentId, null, true, $newCollectiveFolder)) {
			$file = $newFile;
		}
		try {
			$this->updatePage($newCollectiveId, $file->getId(), $userId, $pageInfo->getEmoji(), $pageInfo->isFullWidth());
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		$pageInfo = $this->getPageByFile($file);
		$parentPageInfo = $this->addToSubpageOrder($newCollectiveId, $parentId, $file->getId(), $index, $userId);
		$this->notifyPush(['collectiveId' => $newCollectiveId, 'pages' => [$pageInfo, $parentPageInfo]]);
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function moveToCollective(int $collectiveId, int $id, int $newCollectiveId, ?int $parentId, int $index, string $userId): void {
		$this->verifyEditPermissions($collectiveId, $userId);
		$this->verifyEditPermissions($newCollectiveId, $userId);
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$newCollectiveFolder = $this->getCollectiveFolder($newCollectiveId, $userId);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $id);
		$oldParentId = $this->getParentPageId($file);
		$parentId = $parentId ?: self::getIndexPageFile($newCollectiveFolder)->getId();

		if (null !== $newFile = $this->moveOrCopyPage($collectiveFolder, $file, $parentId, null, false, $newCollectiveFolder)) {
			$file = $newFile;
		}
		try {
			$this->updatePage($newCollectiveId, $file->getId(), $userId, null, null, null, '[]');
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		$oldParentPageInfo = $this->removeFromSubpageOrder($collectiveId, $oldParentId, $id, $userId);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$oldParentPageInfo], 'removed' => [$id]]);
		$pageInfo = $this->getPageByFile($file);
		$newParentPageInfo = $this->addToSubpageOrder($newCollectiveId, $parentId, $file->getId(), $index, $userId);
		$this->notifyPush(['collectiveId' => $newCollectiveId, 'pages' => [$pageInfo, $newParentPageInfo]]);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setEmoji(int $collectiveId, int $id, ?string $emoji, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$folder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageInfo = $this->getPageByFile($file);
		$pageInfo->setLastUserId($userId);
		$pageInfo->setLastUserDisplayName($this->userManager->getDisplayName($userId));
		$pageInfo->setEmoji($emoji);
		$this->updatePage($collectiveId, $pageInfo->getId(), $userId, $emoji);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setSubpageOrder(int $collectiveId, int $id, ?string $subpageOrder, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$folder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageInfo = $this->getPageByFile($file);

		SubpageOrderService::verify($subpageOrder);

		$pageInfo->setSubpageOrder($subpageOrder);
		$this->updateSubpageOrder($collectiveId, $pageInfo->getId(), $userId, $subpageOrder);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function cleanSubpageOrder(int $collectiveId, PageInfo $pageInfo, string $userId): string {
		$pageFile = $this->getPageFile($collectiveId, $pageInfo->getId(), $userId);
		$childIds = array_map(static fn (PageInfo $pageInfo) => $pageInfo->getId(), $this->getSubpagesFromFile($collectiveId, $pageFile, $userId));
		return SubpageOrderService::clean($pageInfo->getSubpageOrder(), $childIds);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function addToSubpageOrder(int $collectiveId, int $pageId, int $addId, int $index, string $userId): PageInfo {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $pageId);
		$pageInfo = $this->getPageByFile($file);

		$subpageOrder = $pageInfo->getSubpageOrder();
		$cleanedSubpageOrder = $this->cleanSubpageOrder($collectiveId, $pageInfo, $userId);
		$newSubpageOrder = SubpageOrderService::add($cleanedSubpageOrder, $addId, $index);

		$pageInfo->setSubpageOrder($newSubpageOrder);
		$this->updateSubpageOrder($collectiveId, $pageInfo->getId(), $userId, $newSubpageOrder);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function removeFromSubpageOrder(int $collectiveId, int $pageId, int $removeId, string $userId): PageInfo {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $pageId);
		$pageInfo = $this->getPageByFile($file);

		$newSubpageOrder = SubpageOrderService::remove($pageInfo->getSubpageOrder(), $removeId);

		$pageInfo->setSubpageOrder($newSubpageOrder);
		$this->updateSubpageOrder($collectiveId, $pageInfo->getId(), $userId, $newSubpageOrder);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function addTag(int $collectiveId, int $id, int $tagId, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);

		$collectiveTags = array_map(static fn ($tag) => $tag->getId(), $this->tagMapper->findAll($collectiveId));
		if (!in_array($tagId, $collectiveTags, true)) {
			throw new NotFoundException('Tag ' . $tagId . ' not found for collective');
		}

		$folder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($folder, $id);

		// A race condition is possible here. If two requests both get the old tags list before writing the new one,
		// the second request will overwrite the change from the first. We decided against implementing complex a
		// check-and-retry solution for now due to unlikeliness and low impact.
		$pageInfo = $this->getPageByFile($file);
		$tags = PageTagHelper::add($pageInfo->getTags(), $tagId, $collectiveTags);
		$pageInfo->setTags($tags);
		$this->updateTags($collectiveId, $pageInfo->getId(), $userId, $tags);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function removeTag(int $collectiveId, int $id, int $tagId, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);

		$collectiveTags = array_map(static fn ($tag) => $tag->getId(), $this->tagMapper->findAll($collectiveId));
		if (!in_array($tagId, $collectiveTags, true)) {
			throw new NotFoundException('Tag ' . $tagId . ' not found for collective');
		}

		$folder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($folder, $id);

		// A race condition is possible here. If two requests both get the old tags list before writing the new one,
		// the second request will overwrite the change from the first. We decided against implementing complex a
		// check-and-retry solution for now due to unlikeliness and low impact.
		$pageInfo = $this->getPageByFile($file);
		$tags = PageTagHelper::remove($pageInfo->getTags(), $tagId, $collectiveTags);
		$pageInfo->setTags($tags);
		$this->updateTags($collectiveId, $pageInfo->getId(), $userId, $tags);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function trash(int $collectiveId, int $id, string $userId, bool $direct = false, ?Folder $folder = null): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$folder = $folder ?: $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageInfo = $this->getPageByFile($file);
		$parentId = $this->getParentPageId($file);

		try {
			if (NodeHelper::isIndexPage($file)) {
				// Delete folder if it's an index page without subpages
				$file->getParent()->delete();
			} else {
				// Delete file if it's not an index page
				$file->delete();
			}
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		$this->initTrashBackend();
		if ($direct || !$this->trashBackend) {
			// Delete directly if desired or trash is not available
			$this->pageMapper->deleteByFileId($id);
			$oldParentPageInfo = $this->removeFromSubpageOrder($collectiveId, $parentId, $id, $userId);
			$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$oldParentPageInfo], 'removed' => [$id]]);
			return $pageInfo;
		}

		$trashedPage = $this->pageMapper->findByFileId($id, true);
		if (!$trashedPage) {
			throw new NotFoundException('Failed to find trashed page in page trash database: ' . $id);
		}

		$pageInfo->setTrashTimestamp($trashedPage->getTrashTimestamp());
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function restore(int $collectiveId, int $id, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);

		$this->initTrashBackend();
		if (!$this->trashBackend) {
			throw new NotPermittedException('Failed to restore page. Trash is disabled.');
		}

		if (null === $trashItem = $this->trashBackend->getTrashItemByCollectiveAndId($this->userManager->get($userId), $collectiveId, $id)) {
			throw new NotFoundException('Failed to restore page ' . $id . '. Not found in trash.');
		}

		try {
			$this->trashBackend->restoreItem($trashItem);
		} catch (FilesNotFoundException|FilesNotPermittedException|InvalidPathException $e) {
			throw new NotFoundException('Failed to restore page ' . $id . ':' . $e->getMessage(), 0, $e);
		}

		$pageInfo = $this->findByFileId($collectiveId, $id, $userId);
		$this->notifyPush(['collectiveId' => $collectiveId, 'pages' => [$pageInfo]]);
		return $pageInfo;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function delete(int $collectiveId, int $id, string $userId): void {
		$this->verifyEditPermissions($collectiveId, $userId);

		$this->initTrashBackend();
		if (!$this->trashBackend) {
			throw new NotPermittedException('Failed to delete page. Trash is disabled.');
		}

		if (null === $trashItem = $this->trashBackend->getTrashItemByCollectiveAndId($this->userManager->get($userId), $collectiveId, $id)) {
			throw new NotFoundException('Failed to delete page ' . $id . '. Not found in trash.');
		}

		try {
			$this->trashBackend->removeItem($trashItem);
		} catch (FilesNotFoundException|FilesNotPermittedException $e) {
			throw new NotFoundException('Failed to delete page from trash ' . $id . ':' . $e->getMessage());
		}

		$this->notifyPush(['collectiveId' => $collectiveId, 'removed' => [$id]]);
	}

	public function getPageLink(string $collectiveUrlPath, PageInfo $pageInfo, bool $withFileId = true, bool $forceNoSlug = false): string {
		$collectiveRoute = rawurlencode($collectiveUrlPath);
		$pagePathRoute = '';
		$fileIdQuery = '';

		if (!$forceNoSlug && $pageInfo->getSlug()) {
			$pageTitleRoute = rawurlencode($pageInfo->getUrlPath());
		} else {
			$pagePathRoute = implode('/', array_map('rawurlencode', explode('/', $pageInfo->getFilePath())));
			$pageTitleRoute = ($pageInfo->getFileName() === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX) ? '' : rawurlencode($pageInfo->getTitle());
			if ($withFileId && $pageInfo->getParentId() !== 0) {
				$fileIdQuery = '?fileId=' . $pageInfo->getId();
			}
		}
		return implode('/', array_filter([
			$collectiveRoute,
			$pagePathRoute,
			$pageTitleRoute
		])) . $fileIdQuery;
	}

	public function matchBacklinks(Collective $collective, PageInfo $pageInfo, string $content): bool {
		$prefix = '/(\[[^]]+]\(|<)';
		$suffix = '(( \([^)]+\))?\)|>)/';

		$protocol = 'https?:\/\/';
		$trustedDomainArray = array_map(static fn (string $domain) => str_replace('\*', '\w*', preg_quote($domain, '/')), (array)$this->config->getSystemValue('trusted_domains', []));
		$trustedDomains = $trustedDomainArray !== [] ? '(' . implode('|', $trustedDomainArray) . ')' : 'localhost';

		$basePath = str_replace('/', '/+', str_replace('/', '/+', preg_quote(trim(OC::$WEBROOT, '/'), '/'))) . '(\/+index\.php)?';

		$relativeUrl = '(?!' . $protocol . '[^\/]+)';
		$absoluteUrl = $protocol . $trustedDomains . '(:[0-9]+)?';

		$appPath = '\/+apps\/+collectives\/+';

		$collectivePath = '(' . implode('|', [
			'[[:ascii:]]+\-' . $collective->getId(),
			rawurlencode($collective->getName()),
		]) . ')\/+';

		$pagePathSlug = $collectivePath . '[[:ascii:]]+\-' . $pageInfo->getId();
		$pagePathNoSlug = $collectivePath . str_replace('/', '/+', preg_quote($this->getPageLink('', $pageInfo, false, true), '/'));
		$fileId = '.+\?fileId=' . $pageInfo->getId();

		$relativeSlugPathPattern = $prefix . $relativeUrl . $basePath . $appPath . $pagePathSlug . $suffix;
		$absoluteSlugPathPattern = $prefix . $absoluteUrl . $basePath . $appPath . $pagePathSlug . $suffix;

		$relativeFileIdPattern = $prefix . $relativeUrl . $fileId . $suffix;
		$absoluteFileIdPattern = $prefix . $absoluteUrl . $basePath . $appPath . $fileId . $suffix;

		$relativeNoSlugPathPattern = $prefix . $relativeUrl . $basePath . $appPath . $pagePathNoSlug . $suffix;
		$absoluteNoSlugPathPattern = $prefix . $absoluteUrl . $basePath . $appPath . $pagePathNoSlug . $suffix;

		$matches = preg_match($relativeSlugPathPattern, $content)
			|| preg_match($relativeFileIdPattern, $content)
			|| preg_match($relativeNoSlugPathPattern, $content)
			|| preg_match($absoluteSlugPathPattern, $content)
			|| preg_match($absoluteFileIdPattern, $content)
			|| preg_match($absoluteNoSlugPathPattern, $content);
		return $matches;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getBacklinks(int $collectiveId, int $id, string $userId): array {
		$page = $this->find($collectiveId, $id, $userId);
		$allPages = $this->findAll($collectiveId, $userId);
		$collective = $this->getCollective($collectiveId, $userId);

		$backlinks = [];
		foreach ($allPages as $p) {
			$file = $this->nodeHelper->getFileById($this->getFolder($collectiveId, $p->getId(), $userId), $p->getId());
			$content = $this->nodeHelper->getContent($file);
			if ($this->matchBacklinks($collective, $page, $content)) {
				$backlinks[] = $p;
			}
		}

		return $backlinks;
	}
}
