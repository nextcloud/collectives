<?php

namespace OCA\Collectives\Service;

use Exception;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\Page;
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
	/** @var CollectiveCircleHelper */
	private $collectiveCircleHelper;

	/**
	 * PageService constructor.
	 *
	 * @param NodeHelper             $nodeHelper
	 * @param CollectiveMapper       $collectiveMapper
	 * @param CollectiveCircleHelper $collectiveCircleHelper
	 */
	public function __construct(NodeHelper $nodeHelper,
								CollectiveMapper $collectiveMapper,
								CollectiveCircleHelper $collectiveCircleHelper
	) {
		$this->nodeHelper = $nodeHelper;
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveCircleHelper = $collectiveCircleHelper;
	}

	/**
	 * @param File   $file
	 *
	 * @return Page
	 * @throws InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 */
	private function getPage(File $file): Page {
		$page = new Page();
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
	 * @return Page[]
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
	 * @return Page
	 * @throws PageDoesNotExistException
	 * @throws NotFoundException
	 */
	public function find(string $userId, int $collectiveId, int $id): Page {
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
	 * @return Page
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function create(string $userId, int $collectiveId, string $title): Page {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		$folder = $this->collectiveMapper->getCollectiveFolder($collective, $userId);
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		$filename = NodeHelper::generateFilename($folder, $safeTitle, self::SUFFIX);

		$file = $folder->newFile($filename . self::SUFFIX);
		$page = new Page();
		$page->fromFile($file);
		return $page;
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return Page
	 * @throws NotFoundException
	 */
	public function rename(string $userId, int $collectiveId, int $id, string $title): Page {
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
	 * @return Page
	 * @throws NotFoundException
	 */
	public function delete(string $userId, int $collectiveId, int $id): Page {
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
