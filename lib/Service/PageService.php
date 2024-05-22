<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use Exception;
use OC;
use OC_Util;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
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

class PageService {
	private const DEFAULT_PAGE_TITLE = 'New Page';

	private ?IQueue $pushQueue = null;
	private ?Collective $collective = null;
	private ?PageTrashBackend $trashBackend = null;

	public function __construct(private IAppManager $appManager,
		private PageMapper $pageMapper,
		private NodeHelper $nodeHelper,
		private CollectiveServiceBase $collectiveService,
		private UserFolderHelper $userFolderHelper,
		private IUserManager $userManager,
		private IConfig $config,
		ContainerInterface $container,
		private SessionService $sessionService) {
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
				return $this->getIndexPageFile($parent->getParent())->getId();
			}

			return $this->getIndexPageFile($parent)->getId();
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws NotFoundException
	 */
	private function getPageByFile(File $file, ?Node $parent = null): PageInfo {
		try {
			$page = $this->pageMapper->findByFileId($file->getId());
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
		$lastUserId = ($page !== null) ? $page->getLastUserId() : null;
		$emoji = ($page !== null) ? $page->getEmoji() : null;
		$subpageOrder = ($page !== null) ? $page->getSubpageOrder() : null;
		$pageInfo = new PageInfo();
		try {
			$pageInfo->fromFile($file,
				$this->getParentPageId($file, $parent),
				$lastUserId,
				$lastUserId ? $this->userManager->getDisplayName($lastUserId) : null,
				$emoji,
				$subpageOrder);
		} catch (FilesNotFoundException | InvalidPathException $e) {
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
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
		$lastUserId = ($page !== null) ? $page->getLastUserId() : null;
		$emoji = ($page !== null) ? $page->getEmoji() : null;
		$subpageOrder = ($page !== null) ? $page->getSubpageOrder() : null;
		$trashTimestamp = ($page !== null) ? $page->getTrashTimestamp(): (int)$timestamp;
		$pageInfo = new PageInfo();
		try {
			$pageInfo->fromFile($file,
				0,
				$lastUserId,
				$lastUserId ? $this->userManager->getDisplayName($lastUserId) : null,
				$emoji,
				$subpageOrder);
			$pageInfo->setTrashTimestamp($trashTimestamp);
			$pageInfo->setFilePath('');
			$pageInfo->setTitle(basename($filename, PageInfo::SUFFIX));
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		return $pageInfo;
	}

	private function notifyPush(int $collectiveId): void {
		if (!$this->pushQueue) {
			return;
		}

		$sessionUsers = $this->sessionService->getSessionUsers($collectiveId);
		foreach ($sessionUsers as $userId) {
			$this->pushQueue->push('notify_custom', [
				'user' => $userId,
				'message' => 'collectives_' . $collectiveId . '_pagelist',
			]);
		}
	}

	private function updatePage(int $collectiveId, int $fileId, string $userId, ?string $emoji = null): void {
		$page = new Page();
		$page->setFileId($fileId);
		$page->setLastUserId($userId);
		if ($emoji !== null) {
			$page->setEmoji($emoji);
		}
		$this->pageMapper->updateOrInsert($page);
		$this->notifyPush($collectiveId);
	}

	/**
	 * @throws NotFoundException
	 */
	private function updateSubpageOrder(int $collectiveId, int $fileId, string $userId, string $subpageOrder): void {
		if (null === $oldPage = $this->pageMapper->findByFileId($fileId)) {
			throw new NotFoundException('page not found');
		}
		$page = new Page();
		$page->setId($oldPage->getId());
		$page->setFileId($fileId);
		$page->setSubpageOrder($subpageOrder);
		$this->pageMapper->update($page);
		$this->notifyPush($collectiveId, $userId);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function newPage(int $collectiveId, Folder $folder, string $filename, string $userId): PageInfo {
		$hasTemplate = NodeHelper::folderHasSubPage($folder, PageInfo::TEMPLATE_PAGE_TITLE);
		try {
			if ($hasTemplate === 1) {
				$template = $folder->get(PageInfo::TEMPLATE_PAGE_TITLE . PageInfo::SUFFIX);
				$newFile = $template->copy($folder->getPath() . '/' . $filename . PageInfo::SUFFIX);
			} elseif ($hasTemplate === 2) {
				$template = $folder->get(PageInfo::TEMPLATE_PAGE_TITLE);
				$newFolder = $template->copy($folder->getPath() . '/' . $filename);
				if ($newFolder instanceof Folder) {
					$newFile = $newFolder->get(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
				} else {
					throw new NotFoundException('Failed to get Template folder');
				}
			} else {
				$newFile = $folder->newFile($filename . PageInfo::SUFFIX);
			}
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		$pageInfo = new PageInfo();
		try {
			$pageInfo->fromFile($newFile,
				$this->getParentPageId($newFile),
				$userId,
				$this->userManager->getDisplayName($userId));
			$this->updatePage($collectiveId, $newFile->getId(), $userId);
		} catch (FilesNotFoundException | InvalidPathException $e) {
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
		} catch (InvalidPathException | FilesNotFoundException | FilesNotPermittedException | LockedException $e) {
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
	private function getIndexPageFile(Folder $folder): File {
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
	public function getPagesFromFolder(int $collectiveId, Folder $folder, string $userId, bool $recurse = false): array {
		// Find index page or create it if we have subpages, but it doesn't exist
		try {
			$indexPage = $this->getPageByFile($this->getIndexPageFile($folder), $folder);
		} catch (NotFoundException) {
			if (!NodeHelper::folderHasSubPages($folder)) {
				return [];
			}
			$indexPage = $this->newPage($collectiveId, $folder, PageInfo::INDEX_PAGE_TITLE, $userId);
		}
		$pageInfos = [$indexPage];

		// Add subpages and recurse over subfolders
		foreach ($folder->getDirectoryListing() as $node) {
			if ($node instanceof File && NodeHelper::isPage($node) && !NodeHelper::isIndexPage($node)) {
				$pageInfos[] = $this->getPageByFile($node, $folder);
			} elseif ($recurse && $node instanceof Folder) {
				array_push($pageInfos, ...$this->getPagesFromFolder($collectiveId, $node, $userId, true));
			}
		}

		return $pageInfos;
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
	public function findAllTrash(int $collectiveId, string $userId): array {
		$this->verifyEditPermissions($collectiveId, $userId);

		$this->initTrashBackend();
		if (!$this->trashBackend) {
			throw new NotPermittedException('Failed to list page trash. Trash is disabled.');
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
	public function findByPath(int $collectiveId, string $path, string $userId): PageInfo {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$landingPageId = $this->getIndexPageFile($collectiveFolder)->getId();

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
	public function findByFileId(int $collectiveId, int $fileId, string $userId): PageInfo {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
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
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function create(int $collectiveId, int $parentId, string $title, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$folder = $this->getFolder($collectiveId, $parentId, $userId);
		$parentFile = $this->nodeHelper->getFileById($folder, $parentId);
		$folder = $this->initSubFolder($parentFile);
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		$filename = NodeHelper::generateFilename($folder, $safeTitle, PageInfo::SUFFIX);

		$pageInfo = $this->newPage($collectiveId, $folder, $filename, $userId);
		$this->addToSubpageOrder($collectiveId, $parentId, $pageInfo->getId(), 0, $userId);
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
		} catch (InvalidPathException | FilesNotFoundException $e) {
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
		} catch (InvalidPathException | FilesNotFoundException | LockedException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		if ($newNode instanceof Folder) {
			// Return index page if node is a folder
			$newNode = $this->getIndexPageFile($newNode);
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
		$page = $this->getPageByFile($file);
		$oldParentId = $this->getParentPageId($file);
		$parentId = $parentId ?: $oldParentId;

		if (null !== $newFile = $this->moveOrCopyPage($collectiveFolder, $file, $parentId, $title, true)) {
			$file = $newFile;
		}
		try {
			$this->updatePage($collectiveId, $file->getId(), $userId, $page->getEmoji());
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		$this->addToSubpageOrder($collectiveId, $parentId, $file->getId(), $index, $userId);

		return $this->getPageByFile($file);
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

		try {
			$this->updatePage($collectiveId, $file->getId(), $userId);
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		if ($oldParentId !== $parentId) {
			// Page got moved: remove from subpage order of old parent page, add to new
			$this->removeFromSubpageOrder($collectiveId, $oldParentId, $id, $userId);
			$this->addToSubpageOrder($collectiveId, $parentId, $file->getId(), $index, $userId);
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
		$page = $this->getPageByFile($file);
		$parentId = $parentId ?: $this->getIndexPageFile($newCollectiveFolder)->getId();

		if (null !== $newFile = $this->moveOrCopyPage($collectiveFolder, $file, $parentId, null, true, $newCollectiveFolder)) {
			$file = $newFile;
		}
		try {
			$this->updatePage($newCollectiveId, $file->getId(), $userId, $page->getEmoji());
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		$this->addToSubpageOrder($newCollectiveId, $parentId, $file->getId(), $index, $userId);
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
		$parentId = $parentId ?: $this->getIndexPageFile($newCollectiveFolder)->getId();

		if (null !== $newFile = $this->moveOrCopyPage($collectiveFolder, $file, $parentId, null, false, $newCollectiveFolder)) {
			$file = $newFile;
		}
		try {
			$this->updatePage($newCollectiveId, $file->getId(), $userId);
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		$this->removeFromSubpageOrder($collectiveId, $oldParentId, $id, $userId);
		$this->addToSubpageOrder($newCollectiveId, $parentId, $file->getId(), $index, $userId);
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
	private function addToSubpageOrder(int $collectiveId, int $pageId, int $addId, int $index, string $userId): void {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $pageId);
		$pageInfo = $this->getPageByFile($file);

		$subpageOrder = $pageInfo->getSubpageOrder();
		$cleanedSubpageOrder = $this->cleanSubpageOrder($collectiveId, $pageInfo, $userId);
		$newSubpageOrder = SubpageOrderService::add($cleanedSubpageOrder, $addId, $index);

		$pageInfo->setSubpageOrder($newSubpageOrder);
		$this->updateSubpageOrder($collectiveId, $pageInfo->getId(), $userId, $newSubpageOrder);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function removeFromSubpageOrder(int $collectiveId, int $pageId, int $removeId, string $userId): void {
		$collectiveFolder = $this->getCollectiveFolder($collectiveId, $userId);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $pageId);
		$pageInfo = $this->getPageByFile($file);

		$newSubpageOrder = SubpageOrderService::remove($pageInfo->getSubpageOrder(), $removeId);

		$pageInfo->setSubpageOrder($newSubpageOrder);
		$this->updateSubpageOrder($collectiveId, $pageInfo->getId(), $userId, $newSubpageOrder);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function trash(int $collectiveId, int $id, string $userId): PageInfo {
		$this->verifyEditPermissions($collectiveId, $userId);
		$folder = $this->getCollectiveFolder($collectiveId, $userId);
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
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		$this->initTrashBackend();
		if (!$this->trashBackend) {
			// Delete directly if trash is not available
			$this->pageMapper->deleteByFileId($id);
			$this->removeFromSubpageOrder($collectiveId, $parentId, $id, $userId);
			$this->notifyPush($collectiveId);
			return $pageInfo;
		}

		$trashedPage = $this->pageMapper->findByFileId($id, true);
		if (!$trashedPage) {
			throw new NotFoundException('Failed to find trashed page in page trash database: ' . $id);
		}

		$pageInfo->setTrashTimestamp($trashedPage->getTrashTimestamp());
		$this->notifyPush($collectiveId);
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

		$this->notifyPush($collectiveId);
		return $this->findByFileId($collectiveId, $id, $userId);
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

		$this->notifyPush($collectiveId);
	}

	public function getPageLink(string $collectiveName, PageInfo $pageInfo, bool $withFileId = true): string {
		$collectiveRoute = rawurlencode($collectiveName);
		$pagePathRoute = implode('/', array_map('rawurlencode', explode('/', $pageInfo->getFilePath())));
		$pageTitleRoute = rawurlencode($pageInfo->getTitle());
		$fullRoute = implode('/', array_filter([
			$collectiveRoute,
			$pagePathRoute,
			$pageTitleRoute
		]));

		return $withFileId ? $fullRoute . '?fileId=' . $pageInfo->getId() : $fullRoute;
	}

	public function matchBacklinks(PageInfo $pageInfo, string $content): bool {
		$prefix = '/(\[[^\]]+\]\(|\<)';
		$suffix = '[\)\>]/';

		$protocol = 'https?:\/\/';
		$trustedDomainArray = array_map(static fn (string $domain) => str_replace('\*', '\w*', preg_quote($domain, '/')), (array)$this->config->getSystemValue('trusted_domains', []));
		$trustedDomains = $trustedDomainArray !== [] ? '(' . implode('|', $trustedDomainArray) . ')' : 'localhost';

		$basePath = str_replace('/', '/+', str_replace('/', '/+', preg_quote(trim(OC::$WEBROOT, '/'), '/'))) . '(\/+index\.php)?';

		$relativeUrl = '(?!' . $protocol . '[^\/]+)';
		$absoluteUrl = $protocol . $trustedDomains . '(:[0-9]+)?';

		$appPath = '\/+apps\/+collectives\/+';

		$pagePath = str_replace('/', '/+', preg_quote($this->getPageLink(explode('/', $pageInfo->getCollectivePath())[1], $pageInfo, false), '/'));
		$fileId = '.+\?fileId=' . $pageInfo->getId();

		$relativeFileIdPattern = $prefix . $relativeUrl . $fileId . $suffix;
		$absoluteFileIdPattern = $prefix . $absoluteUrl . $basePath . $appPath . $fileId . $suffix;

		$relativePathPattern = $prefix . $relativeUrl . $basePath . $appPath . $pagePath . $suffix;
		$absolutePathPattern = $prefix . $absoluteUrl . $basePath . $appPath . $pagePath . $suffix;

		return preg_match($relativeFileIdPattern, $content) ||
			preg_match($relativePathPattern, $content) ||
			preg_match($absoluteFileIdPattern, $content) ||
			preg_match($absolutePathPattern, $content);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getBacklinks(int $collectiveId, int $id, string $userId): array {
		$page = $this->find($collectiveId, $id, $userId);
		$allPages = $this->findAll($collectiveId, $userId);

		$backlinks = [];
		foreach ($allPages as $p) {
			$file = $this->nodeHelper->getFileById($this->getFolder($collectiveId, $p->getId(), $userId), $p->getId());
			$content = NodeHelper::getContent($file);
			if ($this->matchBacklinks($page, $content)) {
				$backlinks[] = $p;
			}
		}

		return $backlinks;
	}
}
