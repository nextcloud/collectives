<?php

declare(strict_types=1);

namespace OCA\Collectives\Trash;

use OC\Files\Storage\Wrapper\Jail;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Mount\CollectiveStorage;
use OCA\Collectives\Mount\MountProvider;
use OCA\Collectives\Versions\VersionsBackend;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\ITrashItem;
use OCA\Files_Trashbin\Trash\TrashItem;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class PageTrashBackend implements ITrashBackend {
	private CollectiveFolderManager $collectiveFolderManager;
	private ?Folder $appFolder = null;
	private PageTrashManager $trashManager;
	private MountProvider $mountProvider;
	private CollectiveMapper $collectiveMapper;
	private PageMapper $pageMapper;
	private LoggerInterface $logger;
	private ?VersionsBackend $versionsBackend = null;

	public function __construct(CollectiveFolderManager $collectiveFolderManager,
		PageTrashManager        $trashManager,
		MountProvider           $mountProvider,
		CollectiveMapper        $collectiveMapper,
		PageMapper              $pageMapper,
		LoggerInterface         $logger) {
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->trashManager = $trashManager;
		$this->mountProvider = $mountProvider;
		$this->collectiveMapper = $collectiveMapper;
		$this->pageMapper = $pageMapper;
		$this->logger = $logger;
	}

	/**
	 * @return Folder
	 */
	private function getAppFolder(): Folder {
		if (!$this->appFolder) {
			$this->appFolder = $this->collectiveFolderManager->getRootFolder();
		}
		return $this->appFolder;
	}

	/**
	 * @param VersionsBackend $versionsBackend
	 */
	public function setVersionsBackend(VersionsBackend $versionsBackend): void {
		$this->versionsBackend = $versionsBackend;
	}

	/**
	 * @param IUser $user
	 *
	 * @return array|CollectivePageTrashItem[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function listTrashRoot(IUser $user): array {
		$folders = $this->mountProvider->getFoldersForUser($user);
		return $this->getTrashForFolders($user, $folders);
	}

	/**
	 * @param ITrashItem $trashItem
	 *
	 * @return array|TrashItem[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function listTrashFolder(ITrashItem $trashItem): array {
		if (!$trashItem instanceof CollectivePageTrashItem) {
			return [];
		}
		$user = $trashItem->getUser();
		$folder = $this->getNodeForTrashItem($user, $trashItem);
		if (!$folder instanceof Folder) {
			return [];
		}
		$content = $folder->getDirectoryListing();
		return array_map(function (Node $node) use ($trashItem, $user) {
			return new CollectivePageTrashItem(
				$this,
				$trashItem->getOriginalLocation() . '/' . $node->getName(),
				$trashItem->getDeletedTime(),
				$trashItem->getTrashPath() . '/' . $node->getName(),
				$node,
				$user,
				$trashItem->getCollectiveMountPoint()
			);
		}, $content);
	}

	/**
	 * @param ITrashItem $item
	 *
	 * @return void
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function restoreItem(ITrashItem $item): void {
		if (!($item instanceof CollectivePageTrashItem)) {
			throw new \LogicException('Trying to restore normal trash item in collective trash backend');
		}
		$user = $item->getUser();
		[, $collectiveId] = explode('/', $item->getTrashPath());
		$collectiveId = (int)$collectiveId;
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}

		$trashStorage = $node->getStorage();
		$targetFolder = $this->collectiveFolderManager->getFolder($collectiveId);
		$originalLocation = $item->getOriginalLocation();
		$parent = dirname($originalLocation);
		if ($parent === '.') {
			$parent = '';
		}
		if ($parent !== '' && !$targetFolder->nodeExists($parent)) {
			$originalLocation = basename($originalLocation);
		}

		if ($targetFolder->nodeExists($originalLocation)) {
			$info = pathinfo($originalLocation);
			$i = 1;

			$gen = static function ($info, int $i): string {
				$target = $info['dirname'];
				if ($target === '.') {
					$target = '';
				}

				$target .= $info['filename'];
				$target .= ' (' . $i . ')';

				if (isset($info['extension'])) {
					$target .= $info['extension'];
				}

				return $target;
			};

			do {
				$originalLocation = $gen($info, $i);
				$i++;
			} while ($targetFolder->nodeExists($originalLocation));
		}

		// Get pageId for restoring page in collective page database
		$restorePageId = $node->getId();
		if ($node instanceof Folder) {
			// Try to use index page if folder is deleted
			try {
				$indexNode = $node->get(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
				$restorePageId = $indexNode->getId();
			} catch (NotFoundException $e) {
			}
		}

		$targetLocation = $targetFolder->getInternalPath() . '/' . $originalLocation;
		$targetFolder->getStorage()->moveFromStorage($trashStorage, $node->getInternalPath(), $targetLocation);
		$targetFolder->getStorage()->getUpdater()->renameFromStorage($trashStorage, $node->getInternalPath(), $targetLocation);
		$this->trashManager->removeItem($collectiveId, $item->getName(), $item->getDeletedTime());

		// Restore page in collective page database
		if ($restorePageId) {
			$this->pageMapper->restoreByFileId($restorePageId);
		}

		// Also restore attachments folder if it exists
		if (null !== $attachmentsFolderItem = $this->findAttachmentFolderItem($user, $collectiveId, $item)) {
			$this->restoreItem($attachmentsFolderItem);
		}
	}

	/**
	 * @param ITrashItem $item
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function removeItem(ITrashItem $item): void {
		if (!($item instanceof CollectivePageTrashItem)) {
			throw new \LogicException('Trying to remove normal trash item in collective trash backend');
		}
		$user = $item->getUser();
		[, $collectiveId] = explode('/', $item->getTrashPath());
		$collectiveId = (int)$collectiveId;
		$node = $this->getNodeForTrashItem($user, $item);
		if ($node === null) {
			throw new NotFoundException();
		}

		// Get pageId for deleting page from collective page database
		$deletePageId = $node->getId();
		if ($node instanceof Folder) {
			// Try to use index page if folder is deleted
			try {
				$indexNode = $node->get(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
				$deletePageId = $indexNode->getId();
			} catch (NotFoundException $e) {
			}
		}

		if ($node->getStorage()->unlink($node->getInternalPath()) === false) {
			throw new \Exception('Failed to remove item from trashbin');
		}

		$node->getStorage()->getCache()->remove($node->getInternalPath());
		if ($item->isRootItem()) {
			$this->trashManager->removeItem($collectiveId, $item->getName(), $item->getDeletedTime());
		}

		if (!is_null($this->versionsBackend)) {
			$this->versionsBackend->deleteAllVersionsForFile($collectiveId, $item->getId());
		}

		// Delete page from collective page database
		if ($deletePageId) {
			$this->pageMapper->deleteByFileId($deletePageId);
		}

		// Also remove attachments folder if it exists
		if (null !== $attachmentsFolderItem = $this->findAttachmentFolderItem($user, $collectiveId, $item)) {
			$this->removeItem($attachmentsFolderItem);
		}
	}

	/**
	 * @param IUser                   $user
	 * @param int                     $collectiveId
	 * @param CollectivePageTrashItem $item
	 *
	 * @return TrashItem|null
	 * @throws NotPermittedException
	 */
	private function findAttachmentFolderItem(IUser $user, int $collectiveId, CollectivePageTrashItem $item): ?TrashItem {
		$attachmentsPrefix = ".attachments.";
		if (strpos($item->getName(), $attachmentsPrefix) === 0) {
			// Passed item is already an attachment folder
			return null;
		}

		$trashFolder = $this->getTrashFolder($collectiveId);
		$deletedTime = $item->getDeletedTime();
		// Search for attachments folder with deleted time up to two seconds after item deleted time
		for ($t = $deletedTime; $t < $deletedTime + 2; $t++) {
			try {
				$name = self::getTrashFilename($attachmentsPrefix . $item->getId(), $t);
				$attachmentsNode = $trashFolder->get($name);
				if (null !== $attachmentsItem = $this->getTrashItemByCollectiveAndId($user, $collectiveId, $attachmentsNode->getId())) {
					return $attachmentsItem;
				}
				break;
			} catch (NotFoundException | InvalidPathException $e) {
			}
		}

		return null;
	}

	/**
	 * @param IStorage $storage
	 * @param string   $internalPath
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function moveToTrash(IStorage $storage, string $internalPath): bool {
		if ($storage->instanceOfStorage(CollectiveStorage::class) && $storage->isDeletable($internalPath)) {
			$name = basename($internalPath);
			$fileEntry = $storage->getCache()->get($internalPath);
			$collectiveId = $storage->getFolderId();
			$trashFolder = $this->getTrashFolder($collectiveId);
			$trashStorage = $trashFolder->getStorage();
			$time = time();
			$trashName = self::getTrashFilename($name, $time);
			[$unJailedStorage, $unJailedInternalPath] = $this->unwrapJails($storage, $internalPath);
			$targetInternalPath = $trashFolder->getInternalPath() . '/' . $trashName;

			// Get pageId for trashing page in collectives page database
			$trashPageId = null;
			if ($fileEntry->getMimeType() === 'httpd/unix-directory') {
				// Try to use index page if folder is deleted
				if ($indexPageEntry = $storage->getCache()->get(rtrim($internalPath, '/') . '/' . PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX)) {
					$trashPageId = $indexPageEntry->getId();
				}
			} else {
				$trashPageId = $fileEntry->getId();
			}

			if ($trashStorage->moveFromStorage($unJailedStorage, $unJailedInternalPath, $targetInternalPath)) {
				$this->trashManager->addTrashItem($collectiveId, $name, $time, $internalPath, $fileEntry->getId());
				if ($trashStorage->getCache()->getId($targetInternalPath) !== $fileEntry->getId()) {
					$trashStorage->getCache()->moveFromCache($unJailedStorage->getCache(), $unJailedInternalPath, $targetInternalPath);
				}
			} else {
				throw new \Exception('Failed to move collective item to trash');
			}

			// Trash page in collectives page database
			if ($trashPageId) {
				$this->pageMapper->trashByFileId($trashPageId);
			}

			return true;
		}
		return false;
	}

	/**
	 * @param IStorage $storage
	 * @param string   $internalPath
	 *
	 * @return array
	 */
	private function unwrapJails(IStorage $storage, string $internalPath): array {
		$unJailedInternalPath = $internalPath;
		$unJailedStorage = $storage;
		while ($unJailedStorage->instanceOfStorage(Jail::class)) {
			$unJailedStorage = $unJailedStorage->getWrapperStorage();
			if ($unJailedStorage instanceof Jail) {
				$unJailedInternalPath = $unJailedStorage->getUnjailedPath($unJailedInternalPath);
			}
		}
		return [$unJailedStorage, $unJailedInternalPath];
	}

	/**
	 * @param IUser $user
	 * @param int   $collectiveId
	 *
	 * @return bool
	 */
	private function userHasAccessToFolder(IUser $user, int $collectiveId): bool {
		$folders = $this->mountProvider->getFoldersForUser($user);
		$writePermissions = Constants::PERMISSION_READ + Constants::PERMISSION_UPDATE + Constants::PERMISSION_CREATE + Constants::PERMISSION_DELETE;
		$writeFolders = array_filter($folders, static function (array $folder) use ($writePermissions) {
			return ($folder['permissions'] & $writePermissions) === $writePermissions;
		});
		$collectiveIds = array_map(static function (array $folder): int {
			return $folder['folder_id'];
		}, $writeFolders);
		return in_array($collectiveId, $collectiveIds);
	}

	/**
	 * @param IUser      $user
	 * @param TrashItem  $trashItem
	 *
	 * @return Node|null
	 * @throws NotPermittedException
	 */
	private function getNodeForTrashItem(IUser $user, TrashItem $trashItem): ?Node {
		[, $collectiveId, $path] = explode('/', $trashItem->getTrashPath(), 3);
		if (!$this->userHasAccessToFolder($user, (int)$collectiveId)) {
			throw new NotPermittedException('No permission to trash for collective');
		}
		$folders = $this->mountProvider->getFoldersForUser($user);
		foreach ($folders as $collectiveFolder) {
			if ($collectiveFolder['folder_id'] === (int)$collectiveId) {
				$trashRoot = $this->getTrashFolder((int)$collectiveId);
				try {
					return $trashRoot->get($path);
				} catch (NotFoundException $e) {
					return null;
				}
			}
		}
		return null;
	}

	/**
	 * @return Folder
	 * @throws NotPermittedException
	 */
	private function getTrashRoot(): Folder {
		try {
			$folder = $this->getAppFolder()->get('trash');
			if (!$folder instanceof Folder) {
				throw new NotPermittedException('Trash root is not a folder');
			}
			return $folder;
		} catch (NotFoundException $e) {
			return $this->getAppFolder()->newFolder('trash');
		}
	}

	/**
	 * @param int $collectiveId
	 *
	 * @return Folder
	 * @throws NotPermittedException
	 */
	private function getTrashFolder(int $collectiveId): Folder {
		try {
			$folder = $this->getTrashRoot()->get((string)$collectiveId);
			if (!$folder instanceof Folder) {
				throw new NotPermittedException('Trash root is not a folder');
			}
			return $folder;
		} catch (NotFoundException $e) {
			return $this->getTrashRoot()->newFolder((string)$collectiveId);
		}
	}

	/**
	 * @param IUser $user
	 * @param array $folders
	 *
	 * @return array
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	private function getTrashForFolders(IUser $user, array $folders): array {
		$collectiveIds = array_map(static function (array $folder): int {
			return $folder['folder_id'];
		}, $folders);
		$rows = $this->trashManager->listTrashForCollectives($collectiveIds);
		$indexedRows = [];
		foreach ($rows as $row) {
			$key = $row['collective_id'] . '/' . $row['name'] . '/' . $row['deleted_time'];
			$indexedRows[$key] = $row;
		}
		$items = [];
		foreach ($folders as $folder) {
			$collectiveId = $folder['folder_id'];
			if (!$this->userHasAccessToFolder($user, (int)$collectiveId)) {
				continue;
			}

			$mountPoint = $folder['mount_point'];
			$trashFolder = $this->getTrashFolder($collectiveId);
			$content = $trashFolder->getDirectoryListing();
			foreach ($content as $item) {
				$pathParts = pathinfo($item->getName());
				$timestamp = (int)substr($pathParts['extension'], 1);
				$name = $pathParts['filename'];
				$key = $collectiveId . '/' . $name . '/' . $timestamp;
				$originalLocation = isset($indexedRows[$key]) ? $indexedRows[$key]['original_location'] : '';

				if (method_exists($item, 'getFileInfo') && null !== $info = $item->getFileInfo()) {
					$info['name'] = $name;
					$items[] = new CollectivePageTrashItem(
						$this,
						$originalLocation,
						$timestamp,
						'/' . $collectiveId . '/' . $item->getName(),
						$info,
						$user,
						$mountPoint
					);
				}
			}
		}
		return $items;
	}

	public function getTrashNodeById(IUser $user, int $fileId): ?Node {
		try {
			/** @var Folder $trashFolder */
			$trashFolder = $this->getAppFolder()->get('trash');
			$storage = $this->getAppFolder()->getStorage();
			$path = $storage->getCache()->getPathById($fileId);
			if (!$path) {
				return null;
			}
			$absolutePath = $this->getAppFolder()->getMountPoint()->getMountPoint() . $path;
			$relativePath = $trashFolder->getRelativePath($absolutePath);
			if (!$relativePath) {
				return null;
			}
			[, $collectiveId, $nameAndTime] = explode('/', $relativePath);

			if ($this->userHasAccessToFolder($user, (int)$collectiveId)) {
				return $trashFolder->get($relativePath);
			}
		} catch (NotFoundException $e) {
		}

		return null;
	}

	/**
	 * @param IUser $user
	 * @param int   $collectiveId
	 * @param int   $fileId
	 *
	 * @return TrashItem|null
	 * @throws NotPermittedException
	 */
	public function getTrashItemByCollectiveAndId(IUser $user, int $collectiveId, int $fileId): ?TrashItem {
		try {
			if (!$this->userHasAccessToFolder($user, $collectiveId)) {
				return null;
			}

			// Build the TrashItem object
			$trashFolder = $this->getTrashFolder($collectiveId);
			$trashNode = $this->getTrashNodeById($user, $fileId);
			// Get parent folder for index pages
			if ($trashNode instanceof File && NodeHelper::isIndexPage($trashNode)) {
				// The extra `get()` is required to resolve the lazy folder from getParent()
				$trashNode = $trashNode->getParent()->get('');
			}
			$trashItem = $this->trashManager->getTrashItemByFileId($trashNode->getId());
			if ($trashItem && method_exists($trashNode, 'getFileInfo')) {
				$pathParts = pathinfo($trashNode->getName());
				$name = $pathParts['filename'];

				if (null === $info = $trashNode->getFileInfo()) {
					throw new NotFoundException();
				}
				$info['name'] = $name;
				return new CollectivePageTrashItem(
					$this,
					$trashItem['original_location'],
					(int)$trashItem['deleted_time'],
					'/' . $collectiveId . '/' . $trashNode->getName(),
					$info,
					$user,
					$trashFolder->getMountPoint()->getMountPoint(),
				);
			}
		} catch (NotFoundException $e) {
		}

		return null;
	}

	/**
	 * @param IUser $user
	 * @param int   $collectiveId
	 *
	 * @return Node[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function listTrashForCollective(IUser $user, int $collectiveId): array {
		if (!$this->userHasAccessToFolder($user, $collectiveId)) {
			return [];
		}

		return $this->getTrashFolder($collectiveId)->getDirectoryListing();
	}

	/**
	 * @param int $collectiveId
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws InvalidPathException
	 */
	public function cleanTrashFolder(int $collectiveId): void {
		$trashFolder = $this->getTrashFolder($collectiveId);

		foreach ($trashFolder->getDirectoryListing() as $node) {
			$node->delete();
		}

		$this->trashManager->emptyTrash($collectiveId);
	}

	/**
	 * @param int $collectiveId
	 *
	 * @return void
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function deleteTrashFolder(int $collectiveId): void {
		$trashRoot = $this->getTrashRoot();
		try {
			$trashFolder = $trashRoot->get((string)$collectiveId);
			$this->cleanTrashFolder($collectiveId);
			$trashFolder->delete();
		} catch (NotFoundException $e) {
			// Folder doesn't exist
		}
	}

	/**
	 * @param Expiration $expiration
	 *
	 * @return int
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function expire(Expiration $expiration): int {
		$count = 0;
		$collectiveIds = array_map(static function ($collective) {
			return $collective->getId();
		}, $this->collectiveMapper->getAll());
		foreach ($collectiveIds as $collectiveId) {
			$trashItems = $this->trashManager->listTrashForCollectives([$collectiveId]);

			$trashFolder = $this->getTrashFolder($collectiveId);
			$nodes = []; // cache
			foreach ($trashItems as $collectiveTrashItem) {
				$nodeName = self::getTrashFilename($collectiveTrashItem['name'], (int)$collectiveTrashItem['deleted_time']);
				try {
					$nodes[$nodeName] = $trashFolder->get($nodeName);
				} catch (NotFoundException $e) {
					$this->trashManager->removeItem($collectiveId, $collectiveTrashItem['name'], (int)$collectiveTrashItem['deleted_time']);
					continue;
				}
			}
			foreach ($trashItems as $collectiveTrashItem) {
				if ($expiration->isExpired($collectiveTrashItem['deleted_time'])) {
					$nodeName = self::getTrashFilename($collectiveTrashItem['name'], (int)$collectiveTrashItem['deleted_time']);
					if (!isset($nodes[$nodeName])) {
						continue;
					}

					$node = $nodes[$nodeName];
					if ($node->getStorage()->unlink($node->getInternalPath()) === false) {
						$this->logger->error("Failed to remove item from trashbin: " . $node->getPath());
						continue;
					}
					// only count up after checking if removal is possible
					$count++;
					$node->getStorage()->getCache()->remove($node->getInternalPath());
					$this->trashManager->removeItem($collectiveId, $collectiveTrashItem['name'], (int)$collectiveTrashItem['deleted_time']);
					if (!is_null($collectiveTrashItem['file_id']) && !is_null($this->versionsBackend)) {
						$this->versionsBackend->deleteAllVersionsForFile($collectiveId, $collectiveTrashItem['file_id']);
					}
				} else {
					break;
				}
			}
		}

		$this->cleanupDeletedFoldersTrash($collectiveIds);

		return $count;
	}

	/**
	 * @param array $collectiveIds
	 *
	 * @return void
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws InvalidPathException
	 */
	private function cleanupDeletedFoldersTrash(array $collectiveIds): void {
		$trashRoot = $this->getTrashRoot();
		foreach ($trashRoot->getDirectoryListing() as $trashFolder) {
			$collectiveId = $trashFolder->getName();
			if (is_numeric($collectiveId)) {
				$collectiveId = (int)$collectiveId;
				if (!in_array($collectiveId, $collectiveIds, true)) {
					$this->cleanTrashFolder($collectiveId);
					$this->getTrashFolder($collectiveId)->delete();
				}
			}
		}
	}

	/**
	 * Copied from OCA\Files_Trashbin\Trashbin::getTrashFilename
	 *
	 * @param string $filename
	 * @param int    $timestamp
	 *
	 * @return string
	 */
	private static function getTrashFilename(string $filename, int $timestamp): string {
		$trashFilename = $filename . '.d' . $timestamp;
		$length = strlen($trashFilename);
		// oc_filecache `name` column has a limit of 250 chars
		$maxLength = 250;
		if ($length > $maxLength) {
			$trashFilename = substr_replace(
				$trashFilename,
				'',
				$maxLength / 2,
				$length - $maxLength
			);
		}
		return $trashFilename;
	}
}
