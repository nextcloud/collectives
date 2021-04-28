<?php

namespace OCA\Collectives\Service;

use Exception;
use OCA\Circles\Exceptions\CircleDoesNotExistException;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageFile;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\AlreadyExistsException;
use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException;

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
	 * @param File   $file
	 *
	 * @return PageFile
	 * @throws InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function getPage(File $file): PageFile {
		$pageFile = new PageFile();
		$page = $this->pageMapper->findByFileId($file->getId());
		$lastUserId = ($page !== null) ? $page->getLastUserId() : null;
		$pageFile->fromFile($file, $lastUserId);
		return $pageFile;
	}

	/**
	 * @param int    $fileId
	 * @param string $userId
	 */
	public function updatePage(int $fileId, string $userId): void {
		$page = new Page();
		$page->setFileId($fileId);
		$page->setLastUserId($userId);
		$this->pageMapper->updateOrInsert($page);
	}

	/**
	 * @param File   $file
	 *
	 * @return bool
	 */
	public function isPage(File $file): bool {
		$name = $file->getName();
		$length = strlen(PageFile::SUFFIX);
		return (substr($name, -$length) === PageFile::SUFFIX);
	}

	/**
	 * @param $e
	 *
	 * @throws NotFoundException
	 */
	public function handleException(Exception $e): void {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException ||
			$e instanceof AlreadyExistsException ||
			$e instanceof PageDoesNotExistException) {
			throw new NotFoundException($e->getMessage());
		}

		throw $e;
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 *
	 * @return array
	 * @throws CircleDoesNotExistException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function findAll(string $userId, int $collectiveId): array {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		$folder = $this->userFolderHelper->getCollectiveFolder(
			$this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId()),
			$userId);
		$pageFiles = [];
		foreach ($folder->getDirectoryListing() as $file) {
			if ($file instanceof File && $this->isPage($file)) {
				$pageFiles[] = $this->getPage($file);
			}
		}
		return $pageFiles;
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param string $search
	 *
	 * @return PageFile[]
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function findByString(string $userId, int $collectiveId, string $search): array {
		$allPageFiles = $this->findAll($userId, $collectiveId);
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
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param int    $id
	 *
	 * @return PageFile
	 * @throws CircleDoesNotExistException
	 * @throws NotFoundException
	 * @throws PageDoesNotExistException
	 */
	public function find(string $userId, int $collectiveId, int $id): PageFile {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		$folder = $this->userFolderHelper->getCollectiveFolder(
			$this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId()),
			$userId);
		return $this->getPage($this->nodeHelper->getFileById($folder, $id));
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param string $title
	 *
	 * @return PageFile
	 * @throws CircleDoesNotExistException
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function create(string $userId, int $collectiveId, string $title): PageFile {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		$folder = $this->userFolderHelper->getCollectiveFolder(
			$this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId()),
			$userId);
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		$filename = NodeHelper::generateFilename($folder, $safeTitle, PageFile::SUFFIX);

		$file = $folder->newFile($filename . PageFile::SUFFIX);

		$pageFile = new PageFile();
		$pageFile->fromFile($file, $userId);
		$this->updatePage($file->getId(), $userId);

		return $pageFile;
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param int    $id
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 */
	public function touch(string $userId, int $collectiveId, int $id): PageFile {
		if (null === $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: ' . $collectiveId);
		}
		try {
			$pageFile = $this->find($userId, $collectiveId, $id);
			$pageFile->setLastUserId($userId);
			$this->updatePage($pageFile->getId(), $userId);
			return $pageFile;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 */
	public function rename(string $userId, int $collectiveId, int $id, string $title): PageFile {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		try {
			$pageFile = $this->find($userId, $collectiveId, $id);

			$folder = $this->userFolderHelper->getCollectiveFolder(
				$this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId()),
				$userId);
			$file = $this->nodeHelper->getFileById($folder, $pageFile->getId());
			$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);

			// Rename file if title changed
			if ($safeTitle . PageFile::SUFFIX !== $file->getName()) {
				$newFilename = NodeHelper::generateFilename($folder, $safeTitle, PageFile::SUFFIX);
				$file->move($folder->getPath() . '/' . $newFilename . PageFile::SUFFIX);
				$pageFile->setTitle($newFilename);
			}

			$this->updatePage($file->getId(), $userId);

			return $this->getPage($file);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param int    $id
	 *
	 * @return PageFile
	 * @throws NotFoundException
	 */
	public function delete(string $userId, int $collectiveId, int $id): PageFile {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		try {
			$pageFile = $this->find($userId, $collectiveId, $id);
			$folder = $this->userFolderHelper->getCollectiveFolder(
				$this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId()),
				$userId);
			$file = $this->nodeHelper->getFileById($folder, $pageFile->getId());
			$file->delete();
			$this->pageMapper->deleteByFileId($pageFile->getId());
			return $pageFile;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}
