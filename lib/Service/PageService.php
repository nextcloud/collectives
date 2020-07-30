<?php

namespace OCA\Wiki\Service;

use Exception;
use OCA\Wiki\Fs\NodeHelper;
use OCA\Wiki\Model\Page;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\AlreadyExistsException;
use OCP\Files\File;
use OCP\Files\NotPermittedException;

class PageService {
	private const SUFFIX = '.md';

	/** @var NodeHelper */
	private $nodeHelper;

	/**
	 * PageService constructor.
	 *
	 * @param NodeHelper $nodeHelper
	 */
	public function __construct(NodeHelper $nodeHelper) {
		$this->nodeHelper = $nodeHelper;
	}

	/**
	 * @param File   $file
	 *
	 * @return Page
	 */
	private function getPage(File $file): Page {
		return Page::fromFile($file);
	}

	/**
	 * @param File   $file
	 *
	 * @return bool
	 */
	private function isPage(File $file): bool {
		$name = $file->getName();
		$length = strlen(self::SUFFIX);
		return (substr($name, -$length) === self::SUFFIX);
	}

	/**
	 * @param string $userId
	 *
	 * @return Page[]
	 * @throws \OCP\Files\NotFoundException
	 */
	public function findAll(string $userId): array {
		$pages = [];
		$folder = $this->nodeHelper->getFolderForUser($userId);
		foreach ($folder->getDirectoryListing() as $file) {
			if ($file instanceof File && $this->isPage($file)) {
				$pages[] = $this->getPage($file);
			}
		}
		return $pages;
	}

	/**
	 * @param $e
	 *
	 * @throws NotFoundException
	 */
	public function handleException($e) {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException ||
			$e instanceof AlreadyExistsException ||
			$e instanceof PageDoesNotExistException) {
			throw new NotFoundException($e->getMessage());
		}

		throw $e;
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Page
	 * @throws PageDoesNotExistException
	 */
	public function find(int $id, string $userId): Page {
		$folder = $this->nodeHelper->getFolderForUser($userId);
		return $this->getPage($this->nodeHelper->getFileById($folder, $id));
	}

	/**
	 * @param string $title
	 * @param string $userId
	 *
	 * @return Page
	 * @throws NotPermittedException
	 */
	public function create(string $title, string $userId): Page {
		$page = new Page();
		$page->setTitle($title);

		$folder = $this->nodeHelper->getFolderForUser($userId);
		$safeTitle = $this->nodeHelper->sanitiseFilename($page->getTitle());
		$filename = NodeHelper::generateFilename($folder, $safeTitle);

		$file = $folder->newFile($filename . self::SUFFIX);
		return Page::fromFile($file);
	}

	/**
	 * @param int    $id
	 * @param string $title
	 * @param string $userId
	 *
	 * @return Page
	 */
	public function rename(int $id, string $title, string $userId): Page {
		try {
			$page = $this->find($id, $userId);
			$page->setTitle($title);

			$folder = $this->nodeHelper->getFolderForUser($userId);
			$file = $this->nodeHelper->getFileById($folder, $page->getId());
			$safeTitle = $this->nodeHelper->sanitiseFilename($page->getTitle());

			// Rename file if title changed
			if ($safeTitle . self::SUFFIX !== $file->getName()) {
				$newFilename = NodeHelper::generateFilename($folder, $safeTitle);
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
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Page
	 */
	public function delete(int $id, string $userId): Page {
		try {
			$page = $this->find($id, $userId);
			$folder = $this->nodeHelper->getFolderForUser($userId);
			$file = $this->nodeHelper->getFileById($folder, $page->getId());
			$file->delete();
			return $page;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}
