<?php

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageFile;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Lock\LockedException;

class PageService {
	private const DEFAULT_PAGE_TITLE = 'New Page';

	/** @var PageMapper */
	private $pageMapper;

	/** @var NodeHelper */
	private $nodeHelper;

	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var UserFolderHelper */
	private $userFolderHelper;

	/**
	 * PageService constructor.
	 *
	 * @param PageMapper       $pageMapper
	 * @param NodeHelper       $nodeHelper
	 * @param CollectiveMapper $collectiveMapper
	 * @param UserFolderHelper $userFolderHelper
	 */
	public function __construct(PageMapper $pageMapper,
								NodeHelper $nodeHelper,
								CollectiveMapper $collectiveMapper,
								UserFolderHelper $userFolderHelper) {
		$this->pageMapper = $pageMapper;
		$this->nodeHelper = $nodeHelper;
		$this->collectiveMapper = $collectiveMapper;
		$this->userFolderHelper = $userFolderHelper;
	}


	/**
	 * @param string     $userId
	 * @param Collective $collective
	 *
	 * @return Folder
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getCollectiveFolder(string $userId, Collective $collective): Folder {
		try {
			$collectiveName = $this->collectiveMapper->circleIdToName($collective->getCircleId());
			$folder = $this->userFolderHelper->get($userId)->get($collectiveName);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}

		if (!($folder instanceof Folder)) {
			throw new NotFoundException('Folder not found for collective ' . $collective->getId());
		}
		return $folder;
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $fileId
	 *
	 * @return Folder
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getFolder(string $userId, Collective $collective, int $fileId): Folder {
		$collectiveFolder = $this->getCollectiveFolder($userId, $collective);
		if ($fileId === 0) {
			return $collectiveFolder;
		}

		$file = $this->nodeHelper->getFileById($collectiveFolder, $fileId);
		if (!($file instanceof File) || !($file->getParent() instanceof Folder)) {
			throw new NotFoundException("Error getting parent folder for file " . $fileId . " in collective " . $collective->getId());
		}

		return $file->getParent();
	}

	/**
	 * @param File $file
	 *
	 * @return int
	 * @throws NotFoundException
	 */
	private function getParentPageId(File $file): int {
		try {
			if (self::isLandingPage($file)) {
				// Return `0` for landing page
				return 0;
			}

			if (self::isIndexPage($file)) {
				// Go down two levels if index page but not landing page
				return $this->getIndexPageFile($file->getParent()->getParent())->getId();
			}

			return $this->getIndexPageFile($file->getParent())->getId();
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param File $file
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 */
	private function getPageByFile(File $file): PageFile {
		$pageFile = new PageFile();
		try {
			$page = $this->pageMapper->findByFileId($file->getId());
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}
		$lastUserId = ($page !== null) ? $page->getLastUserId() : null;
		try {
			$pageFile->fromFile($file, $this->getParentPageId($file), $lastUserId);
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException($e->getMessage());
		}

		return $pageFile;
	}

	/**
	 * @param string $userId
	 * @param int    $fileId
	 */
	private function updatePage(string $userId, int $fileId): void {
		$page = new Page();
		$page->setFileId($fileId);
		$page->setLastUserId($userId);
		$this->pageMapper->updateOrInsert($page);
	}

	/**
	 * @param string $userId
	 * @param Folder $folder
	 * @param string $filename
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function newPageFile(string $userId, Folder $folder, string $filename): PageFile {
		try {
			$file = $folder->newFile($filename . PageFile::SUFFIX);
			$pageFile = new PageFile();
			$pageFile->fromFile($file, $this->getParentPageId($file), $userId);
			$this->updatePage($userId, $file->getId());
		} catch (FilesNotFoundException | FilesNotPermittedException | InvalidPathException $e) {
			throw new NotPermittedException($e->getMessage());
		}

		return $pageFile;
	}

	/**
	 * @param File $file
	 *
	 * @return Folder
	 * @throws NotPermittedException
	 */
	public function initSubFolder(File $file): Folder {
		$folder = $file->getParent();
		if (self::isIndexPage($file)) {
			return $folder;
		}

		try {
			$folderName = NodeHelper::generateFilename($folder, basename($file->getName(), PageFile::SUFFIX));
			$subFolder = $folder->newFolder($folderName);
			$file->move($subFolder->getPath() . '/' . PageFile::INDEX_PAGE_TITLE . PageFile::SUFFIX);
		} catch (InvalidPathException | FilesNotFoundException | FilesNotPermittedException | LockedException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		return $subFolder;
	}

	/**
	 * @param Folder $folder
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function revertSubFolders(Folder $folder): void {
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof Folder) {
					$this->revertSubFolders($node);
				} elseif ($node instanceof File) {
					// Move index page without subpages into the parent folder (if's not the landing page)
					if (self::isIndexPage($node) && !self::isLandingPage($node) && !$this->hasSubPages($node)) {
						$filename = NodeHelper::generateFilename($folder, $folder->getName(), PageFile::SUFFIX);
						$node->move($folder->getParent()->getPath() . '/' . $filename . PageFile::SUFFIX);
						$folder->delete();
						break;
					}
				}
			}
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException | LockedException $e) {
			throw new NotPermittedException($e->getMessage());
		}
	}

	/**
	 * @param File $file
	 *
	 * @return bool
	 */
	public static function isPage(File $file): bool {
		$name = $file->getName();
		$length = strlen(PageFile::SUFFIX);
		return (substr($name, -$length) === PageFile::SUFFIX);
	}

	/**
	 * @param File $file
	 *
	 * @return bool
	 */
	public static function isLandingPage(File $file): bool {
		$internalPath = $file->getInternalPath();
		return ($internalPath === PageFile::INDEX_PAGE_TITLE . PageFile::SUFFIX);
	}

	/**
	 * @param File $file
	 *
	 * @return bool
	 */
	public static function isIndexPage(File $file): bool {
		$name = $file->getName();
		return ($name === PageFile::INDEX_PAGE_TITLE . PageFile::SUFFIX);
	}

	/**
	 * @param Folder $folder
	 *
	 * @return File
	 * @throws NotFoundException
	 */
	private function getIndexPageFile(Folder $folder): File {
		try {
			$file = $folder->get(PageFile::INDEX_PAGE_TITLE . PageFile::SUFFIX);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}

		if (!($file instanceof File)) {
			throw new NotFoundException('Failed to get index page');
		}

		return $file;
	}

	/**
	 * @param File $file
	 *
	 * @return bool
	 */
	private function hasSubPages(File $file): bool {
		try {
			foreach ($file->getParent()->getDirectoryListing() as $node) {
				if ($node->getName() !== PageFile::INDEX_PAGE_TITLE . PageFile::SUFFIX) {
					return true;
				}
			}
		} catch (FilesNotFoundException $e) {
		}

		return false;
	}

	/**
	 * @param string $userId
	 * @param Folder $folder
	 *
	 * @return array
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function recurseFolder(string $userId, Folder $folder): array {
		try {
			$indexPage = $this->getPageByFile($this->getIndexPageFile($folder));
		} catch (NotFoundException $e) {
			$indexPage = $this->newPageFile($userId, $folder, PageFile::INDEX_PAGE_TITLE);
		}
		$pageFiles = [$indexPage];

		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof File && self::isPage($node) && !self::isIndexPage($node)) {
					$pageFiles[] = $this->getPageByFile($node);
				} elseif ($node instanceof Folder) {
					array_push($pageFiles, ...$this->recurseFolder($userId, $node));
				}
			}
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}
		return $pageFiles;
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 *
	 * @return array
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findAll(string $userId, Collective $collective): array {
		$folder = $this->getCollectiveFolder($userId, $collective);
		try {
			return $this->recurseFolder($userId, $folder);
		} catch (NotPermittedException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param string     $search
	 *
	 * @return PageFile[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findByString(string $userId, Collective $collective, string $search): array {
		$allPageFiles = $this->findAll($userId, $collective);
		$pageFiles = [];
		foreach ($allPageFiles as $page) {
			if (stripos($page->getFileName(), $search) === false) {
				continue;
			}
			$pageFiles[] = $page;
		}

		return $pageFiles;
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function find(string $userId, Collective $collective, int $parentId, int $id): PageFile {
		$folder = $this->getFolder($userId, $collective, $parentId);
		return $this->getPageByFile($this->nodeHelper->getFileById($folder, $id));
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param string     $title
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function create(string $userId, Collective $collective, int $parentId, string $title): PageFile {
		$folder = $this->getFolder($userId, $collective, $parentId);
		$parentFile = $this->nodeHelper->getFileById($folder, $parentId);
		$folder = $this->initSubFolder($parentFile);
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		$filename = NodeHelper::generateFilename($folder, $safeTitle, PageFile::SUFFIX);

		return $this->newPageFile($userId, $folder, $filename);
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function touch(string $userId, Collective $collective, int $parentId, int $id): PageFile {
		$folder = $this->getFolder($userId, $collective, $parentId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageFile = $this->getPageByFile($file);
		$pageFile->setLastUserId($userId);
		$this->updatePage($userId, $pageFile->getId());
		return $pageFile;
	}

	/**
	 * @param Folder $collectiveFolder
	 * @param int    $parentId
	 * @param File   $file
	 * @param string $title
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function renamePage(Folder $collectiveFolder, int $parentId, File $file, string $title): bool {
		$moveFolder = false;
		if ($parentId !== $this->getParentPageId($file)) {
			$newFolder = $this->initSubFolder($this->nodeHelper->getFileById($collectiveFolder, $parentId));
			$moveFolder = true;
		} else {
			$newFolder = $this->nodeHelper->getFileById($collectiveFolder, $parentId)->getParent();
		}

		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		// If processing an index page, then rename the parent folder, otherwise the file itself
		$node = self::isIndexPage($file) ? $file->getParent() : $file;
		$suffix = self::isIndexPage($file) ? '' : PageFile::SUFFIX;
		$newSafeName = $safeTitle . $suffix;

		// Neither path nor title changed, nothing to do
		if (!$moveFolder && $newSafeName === $node->getName()) {
			return false;
		}

		$newFileName = NodeHelper::generateFilename($newFolder, $safeTitle, PageFile::SUFFIX);
		try {
			$node->move($newFolder->getPath() . '/' . $newFileName . $suffix);
		} catch (InvalidPathException | FilesNotFoundException | LockedException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage());
		}

		return true;
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 * @param string     $title
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function rename(string $userId, Collective $collective, int $parentId, int $id, string $title): PageFile {
		$collectiveFolder = $this->getCollectiveFolder($userId, $collective);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $id);
		if ($this->renamePage($collectiveFolder, $parentId, $file, $title)) {
			// Refresh the file after it has been renamed
			$file = $this->nodeHelper->getFileById($collectiveFolder, $id);
		}
		try {
			$this->updatePage($userId, $file->getId());
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}

		$this->revertSubFolders($collectiveFolder);
		return $this->getPageByFile($file);
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function delete(string $userId, Collective $collective, int $parentId, int $id): PageFile {
		$folder = $this->getFolder($userId, $collective, $parentId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageFile = $this->getPageByFile($file);

		try {
			if (self::isIndexPage($file)) {
				// Don't delete if still page has subpages
				if ($this->hasSubPages($file)) {
					throw new NotPermittedException('Failed to delete page ' . $id . ' with subpages');
				} else {
					// Delete folder if it's an index page without subpages
					$file->getParent()->delete();
				}
			} else {
				// Delete file if it's not an index page
				$file->delete();
			}
		} catch (InvalidPathException | FilesNotFoundException | FilesNotPermittedException $e) {
			throw new NotFoundException($e->getMessage());
		}
		$this->pageMapper->deleteByFileId($pageFile->getId());

		$this->revertSubFolders($folder);
		return $pageFile;
	}
}
