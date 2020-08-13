<?php

namespace OCA\Wiki\Service;

use Exception;
use OCA\Wiki\Db\WikiMapper;
use OCA\Wiki\Fs\NodeHelper;
use OCA\Wiki\Model\Page;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\QueryException;
use OCP\Files\AlreadyExistsException;
use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException;

class PageService {
	private const DEFAULT_PAGE_TITLE = 'New Page';
	private const SUFFIX = '.md';

	/** @var NodeHelper */
	private $nodeHelper;
	/** @var WikiMapper */
	private $wikiMapper;
	/** @var WikiCircleHelper */
	private $wikiCircleHelper;

	/**
	 * PageService constructor.
	 *
	 * @param NodeHelper       $nodeHelper
	 * @param WikiMapper       $wikiMapper
	 * @param WikiCircleHelper $wikiCircleHelper
	 */
	public function __construct(NodeHelper $nodeHelper,
								WikiMapper $wikiMapper,
								WikiCircleHelper $wikiCircleHelper
	) {
		$this->nodeHelper = $nodeHelper;
		$this->wikiMapper = $wikiMapper;
		$this->wikiCircleHelper = $wikiCircleHelper;
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
	 * @param int    $wikiId
	 *
	 * @return Page[]
	 * @throws \OCP\Files\NotFoundException
	 * @throws NotFoundException
	 */
	public function findAll(string $userId, int $wikiId): array {
		$this->wikiCircleHelper->userHasWiki($userId, $wikiId);
		$pages = [];
		$folder = $this->wikiMapper->getWikiFolder($wikiId);
		foreach ($folder->getDirectoryListing() as $file) {
			if ($file instanceof File && $this->isPage($file)) {
				$pages[] = $this->getPage($file);
			}
		}
		return $pages;
	}

	/**
	 * @param string $userId
	 * @param int    $wikiId
	 * @param int    $id
	 *
	 * @return Page
	 * @throws PageDoesNotExistException
	 * @throws NotFoundException
	 */
	public function find(string $userId, int $wikiId, int $id): Page {
		$this->wikiCircleHelper->userHasWiki($userId, $wikiId);
		$folder = $this->wikiMapper->getWikiFolder($wikiId);
		return $this->getPage($this->nodeHelper->getFileById($folder, $id));
	}

	/**
	 * @param string $userId
	 * @param int    $wikiId
	 * @param string $title
	 *
	 * @return Page
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotFoundException
	 */
	public function create(string $userId, int $wikiId, string $title): Page {
		$folder = $this->wikiMapper->getWikiFolder($wikiId);
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		$filename = NodeHelper::generateFilename($folder, $safeTitle, self::SUFFIX);

		$file = $folder->newFile($filename . self::SUFFIX);
		$page = new Page();
		$page->fromFile($file);
		return $page;
	}

	/**
	 * @param string $userId
	 * @param int    $wikiId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return Page
	 * @throws NotFoundException
	 */
	public function rename(string $userId, int $wikiId, int $id, string $title): Page {
		$this->wikiCircleHelper->userHasWiki($userId, $wikiId);
		try {
			$page = $this->find($userId, $wikiId, $id);

			$folder = $this->wikiMapper->getWikiFolder($wikiId);
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
	 * @param int    $wikiId
	 * @param int    $id
	 *
	 * @return Page
	 * @throws NotFoundException
	 */
	public function delete(string $userId, int $wikiId, int $id): Page {
		$this->wikiCircleHelper->userHasWiki($userId, $wikiId);
		try {
			$page = $this->find($userId, $wikiId, $id);
			$folder = $this->wikiMapper->getWikiFolder($wikiId);
			$file = $this->nodeHelper->getFileById($folder, $page->getId());
			$file->delete();
			return $page;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}
