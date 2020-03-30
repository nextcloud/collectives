<?php

namespace OCA\Wiki\Fs;

use OCA\Pages\Service\PagesFolderException;
use OCA\Wiki\Db\Page;
use OCA\Wiki\Service\PageDoesNotExistException;
use OCP\Files\AlreadyExistsException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;

class PageMapper {
	private const SUFFIX = '.md';
	private const WIKI_FOLDER = 'Wiki';

	private $root;
	private $userId;

	public function __construct(
		IDBConnection $db,
		IRootFolder $root,
		string $userId) {
		$this->root = $root;
		$this->userId = $userId;
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 * @throws NotFoundException
	 * @throws PagesFolderException
	 * @throws \OCP\Files\NotPermittedException
	 */
	private function getFolderForUser(string $userId): Folder {
		$path = '/' . $userId . '/files/' . self::WIKI_FOLDER;
		return $this->getOrCreateFolder($path);
	}

	/**
	 * @param string $path
	 *
	 * @return Folder
	 * @throws NotFoundException
	 * @throws PagesFolderException
	 * @throws \OCP\Files\NotPermittedException
	 */
	private function getOrCreateFolder(string $path): Folder {
		if ($this->root->nodeExists($path)) {
			$folder = $this->root->get($path);
		} else {
			$folder = $this->root->newFolder($path);
		}
		if (!($folder instanceof Folder)) {
			throw new PagesFolderException($path.' is not a folder');
		}
		return $folder;
	}

	/**
	 * @param Folder $folder
	 * @param int    $id
	 *
	 * @return File
	 * @throws PageDoesNotExistException
	 */
	private function getFileById(Folder $folder, int $id): File {
		$file = $folder->getById($id);

		if (count($file) <= 0 || !($file[0] instanceof File)) {
			throw new PageDoesNotExistException();
		}
		return $file[0];
	}

	/**
	 * @param File   $file
	 * @param Folder $pagesFolder
	 *
	 * @return Page
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	private function getPage(File $file, Folder $pagesFolder): Page {
		$id = $file->getId();
		return Page::fromFile($file, $pagesFolder, $this->userId);
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Page
	 * @throws \Exception
	 */
	public function find(int $id, string $userId): Page {
		$folder = $this->getFolderForUser($userId);
		//var_dump($folder);
		return $this->getPage($this->getFileById($folder, $id), $folder);
	}

	/**
	 * @param string $userId
	 *
	 * @return Page[]
	 * @throws \Exception
	 */
	public function findAll(string $userId): array {
		$pages = [];
		$folder = $this->getFolderForUser($userId);
		foreach ($folder->getDirectoryListing() as $page) {
			$pages[] = $this->getPage($page, $folder);
		}
		return $pages;
	}

	/**
	 * @param Page $page
	 *
	 * @return Page
	 * @throws AlreadyExistsException
	 * @throws NotFoundException
	 * @throws \OCP\Files\GenericFileException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	public function insert(Page $page): Page {
		$folder = $this->getFolderForUser($page->getUserId());
		$filename = $page->getTitle() . self::SUFFIX;
		if ($folder->nodeExists($filename)) {
			throw new AlreadyExistsException();
		}

		$file = $folder->newFile($filename);
		$page->setId($file->getId());
		$file->putContent($page->getContent());
		return $page;
	}

	/**
	 * @param Page $page
	 *
	 * @return Page
	 * @throws NotFoundException
	 * @throws PageDoesNotExistException
	 * @throws \OCP\Files\InvalidPathException
	 */
	public function update(Page $page): Page {
		$folder = $this->getFolderForUser($page->getUserId());
		$filename = $page->getTitle() . self::SUFFIX;
		$file = $folder->get($filename);
		if (!$folder->nodeExists($filename) || $file->getID() !== $page->getId()) {
			throw new PageDoesNotExistException();
		}

		$file->putContent($page->getContent());
		return $page;
	}

	/**
	 * @param Page $page
	 *
	 * @throws PageDoesNotExistException
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function delete(Page $page): void {
		$folder = $this->getFolderForUser($page->getUserId());
		$file = $this->getFileById($folder, $page->getId());
		$file->delete();
	}
}
