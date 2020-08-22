<?php

namespace OCA\Collectives\Service;

use Exception;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageFile;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\AlreadyExistsException;
use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException;

class PageService {
	private const DEFAULT_PAGE_TITLE = 'New Page';
	private const SUFFIX = '.md';

	/** @var NodeHelper */
	private $nodeHelper;
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/**
	 * PageService constructor.
	 *
	 * @param NodeHelper             $nodeHelper
	 * @param CollectiveMapper       $collectiveMapper
	 */
	public function __construct(NodeHelper $nodeHelper,
								CollectiveMapper $collectiveMapper
	) {
		$this->nodeHelper = $nodeHelper;
		$this->collectiveMapper = $collectiveMapper;
	}

	/**
	 * @param File   $file
	 *
	 * @return PageFile
	 * @throws InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function getPage(File $file): PageFile {
		$page = new PageFile();
		$page->fromFile($file);
		return $page;
	}

	/**
	 * @param File   $file
	 *
	 * @return bool
	 */
	public function isPage(File $file): bool {
		$name = $file->getName();
		$length = strlen(self::SUFFIX);
		return (substr($name, -$length) === self::SUFFIX);
	}

	/**
	 * @param $e
	 *
	 * @throws NotFoundException
	 */
	public function handleException($e): void {
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
	 * @return PageFile[]
	 * @throws \OCP\Files\NotFoundException
	 * @throws NotFoundException
	 */
	public function findAll(string $userId, int $collectiveId): array {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		$folder = $this->collectiveMapper->getCollectiveFolder($collective, $userId);
		$pages = [];
		foreach ($folder->getDirectoryListing() as $file) {
			if ($file instanceof File && $this->isPage($file)) {
				$pages[] = $this->getPage($file);
			}
		}
		return $pages;
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param int    $id
	 *
	 * @return PageFile
	 * @throws PageDoesNotExistException
	 * @throws NotFoundException
	 */
	public function find(string $userId, int $collectiveId, int $id): PageFile {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		$folder = $this->collectiveMapper->getCollectiveFolder($collective, $userId);
		return $this->getPage($this->nodeHelper->getFileById($folder, $id));
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param string $title
	 *
	 * @return PageFile
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function create(string $userId, int $collectiveId, string $title): PageFile {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		$folder = $this->collectiveMapper->getCollectiveFolder($collective, $userId);
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		$filename = NodeHelper::generateFilename($folder, $safeTitle, self::SUFFIX);

		$file = $folder->newFile($filename . self::SUFFIX);
		$page = new PageFile();
		$page->fromFile($file);
		return $page;
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
			$page = $this->find($userId, $collectiveId, $id);

			$folder = $this->collectiveMapper->getCollectiveFolder($collective, $userId);
			$file = $this->nodeHelper->getFileById($folder, $page->getId());
			$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);

			// Rename file if title changed
			if ($safeTitle . self::SUFFIX !== $file->getName()) {
				$newFilename = NodeHelper::generateFilename($folder, $safeTitle, self::SUFFIX);
				try {
					$file->move($folder->getPath() . '/' . $newFilename . self::SUFFIX);
				} catch (NotPermittedException $e) {
					$err = 'Moving page ' . $page->getId() . ' (' . $newFilename . self::SUFFIX . ') to the desired target is not allowed.';
					//$this->logger->error($err, ['app' => $this->appName]);
				}
				$page->setTitle($newFilename);
			}

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
			$page = $this->find($userId, $collectiveId, $id);
			$folder = $this->collectiveMapper->getCollectiveFolder($collective, $userId);
			$file = $this->nodeHelper->getFileById($folder, $page->getId());
			$file->delete();
			return $page;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}
