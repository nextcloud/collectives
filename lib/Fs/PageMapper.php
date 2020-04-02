<?php

namespace OCA\Wiki\Fs;

use OCA\Pages\Service\PagesFolderException;
use OCA\Wiki\Db\Page;
use OCA\Wiki\Service\PageDoesNotExistException;
use OCP\Files\AlreadyExistsException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;
use OCP\ILogger;

class PageMapper {
	private const SUFFIX = '.md';
	private const WIKI_FOLDER = 'Wiki';

	private $root;
	private $logger;
	private $userId;
	private $appName;

	public function __construct(
		IDBConnection $db,
		IRootFolder $root,
		ILogger $logger,
		string $userId,
		string $appName) {
		$this->root = $root;
		$this->logger = $logger;
		$this->userId = $userId;
		$this->appName = $appName;
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 */
	private function getFolderForUser(string $userId): Folder {
		$path = '/' . $userId . '/files/' . self::WIKI_FOLDER;
		return $this->getOrCreateFolder($path);
	}

	/**
	 * @param string $path
	 *
	 * @return Folder
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
	 */
	private function getFileById(Folder $folder, int $id): File {
		$file = $folder->getById($id);

		if (count($file) <= 0 || !($file[0] instanceof File)) {
			throw new PageDoesNotExistException('page does not exist');
		}
		return $file[0];
	}

	/**
	 * @param File   $file
	 * @param Folder $pagesFolder
	 *
	 * @return Page
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
	 */
	public function find(int $id, string $userId): Page {
		$folder = $this->getFolderForUser($userId);
		//var_dump($folder);
		return $this->getPage($this->getFileById($folder, $id), $folder);
	}

	/**
	 * @param string $userId
	 *
	 * @return array
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
	 */
	public function insert(Page $page): Page {
		$folder = $this->getFolderForUser($page->getUserId());
		$filename = $page->getTitle() . self::SUFFIX;
		if ($folder->nodeExists($filename)) {
			throw new AlreadyExistsException('page ' . $filename . ' already exists');
		}

		$file = $folder->newFile($filename);
		$page->setId($file->getId());
		$file->putContent($page->getContent());
		return $page;
	}

	/**
	 * Updates a note. Be sure to check the returned note since the title is
	 * dynamically generated and filename conflicts are are resolved
	 *
	 * @param Page $page
	 *
	 * @return Page
	 * @throws PageDoesNotExistException if note does not exist
	 */
	public function update(Page $page): Page {
		$folder = $this->getFolderForUser($page->getUserId());
		$file = $this->getFileById($folder, $page->getId());

		// Rename file if title changed
		$newFilename = $page->getTitle() . self::SUFFIX;
		if ($newFilename !== $file->getName()) {
			try {
				$file->move($folder->getPath() . '/' . $newFilename);
			} catch (NotPermittedException $e) {
				$err = 'Moving page ' . $page->getId() . ' (' . $newFilename . ') to the desired targed is not allowed.';
				$this->logger->error($err, ['app' => $this->appName]);
			}
		}


		$file->putContent($page->getContent());

		return $this->getPage($file, $folder);
	}

	/**
	 * @param Page $page
	 */
	public function delete(Page $page): void {
		$folder = $this->getFolderForUser($page->getUserId());
		$file = $this->getFileById($folder, $page->getId());
		$file->delete();
	}
}
