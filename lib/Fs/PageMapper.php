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
use OCP\IL10N;
use OCP\ILogger;

class PageMapper {
	private const SUFFIX = '.md';
	private const WIKI_FOLDER = 'Wiki';

	private $db;
	private $l10n;
	private $root;
	private $logger;
	private $appName;

	public function __construct(
		IDBConnection $db,
		IL10N $l10n,
		IRootFolder $root,
		ILogger $logger,
		string $appName) {
		$this->db = $db;
		$this->l10n = $l10n;
		$this->root = $root;
		$this->logger = $logger;
		$this->appName = $appName;
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 * @throws PagesFolderException
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
	 *
	 * @return Page
	 */
	private function getPage(File $file): Page {
		$id = $file->getId();
		return Page::fromFile($file);
	}

	/**
	 * @param Folder $folder
	 * @param string $filename
	 *
	 * @return string
	 */
	public static function generateFilename(Folder $folder, string $filename): string {
		$path = $filename . self::SUFFIX;
		if (!$folder->nodeExists($path)) {
			return $filename;
		}

		// Append ' (#)' if filename conflict ('#' starting at 2 and incremented if necessary)
		$match = preg_match('/\((?P<id>\d+)\)$/u', $filename, $matches);
		if ($match) {
			$newId = ((int) $matches['id']) + 1;
			$newFilename = preg_replace(
				'/(.*)\s\((\d+)\)$/u',
				'$1 (' . $newId . ')',
				$filename
			);
		} else {
			$newFilename = $filename . ' (2)';
		}

		return self::generateFilename($folder, $newFilename);
	}

	/**
	 * Removes characters that are illegal in a file or folder name on some operating systems.
	 * Most code copied from `Service::NoteUtil::sanitisePath()` from Notes App.
	 *
	 * @param string $title
	 *
	 * @return string
	 */
	public function sanitiseTitle(string $title): string {
		// remove characters which are illegal on Windows (includes illegal characters on Unix/Linux)
		// prevents also directory traversal by eliminiating slashes
		// see also \OC\Files\Storage\Common::verifyPosixPath(...)
		$title = str_replace(['*', '|', '/', '\\', ':', '"', '<', '>', '?'], '', $title);

		// if mysql doesn't support 4byte UTF-8, then remove those characters
		// see \OC\Files\Storage\Common::verifyPath(...)
		if (!$this->db->supports4ByteText()) {
			$title = preg_replace('%(?:
                \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
              | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
              | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
              )%xs', '', $title);
		}

		// remove leading spaces or dots to prevent hidden files
		$title = preg_replace('/^[\. ]+/mu', '', $title);

		// remove leading and appending spaces
		$title = trim($title);

		if (empty($title)) {
			$title = $this->l10n->t('New Page');
		}

		return $title;
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Page
	 */
	public function find(int $id, string $userId): Page {
		$folder = $this->getFolderForUser($userId);
		return $this->getPage($this->getFileById($folder, $id));
	}

	/**
	 * @param string $userId
	 *
	 * @return array
	 */
	public function findAll(string $userId): array {
		$pages = [];
		$folder = $this->getFolderForUser($userId);
		foreach ($folder->getDirectoryListing() as $file) {
			$pages[] = $this->getPage($file);
		}
		return $pages;
	}

	/**
	 * @param Page $page
	 * @param string $userId
	 *
	 * @return Page
	 */
	public function insert(Page $page, string $userId): Page {
		$folder = $this->getFolderForUser($userId);
		$safeTitle = $this->sanitiseTitle($page->getTitle());
		$filename = self::generateFilename($folder, $safeTitle);

		$file = $folder->newFile($filename . self::SUFFIX);
		$page->setId($file->getId());
		$page->setTitle($filename);
		$file->putContent($page->getContent());
		return $page;
	}

	/**
	 * Updates a note. Be sure to check the returned note since the title is
	 * dynamically generated and filename conflicts are are resolved
	 *
	 * @param Page $page
	 * @param string $userId
	 *
	 * @return Page
	 * @throws PageDoesNotExistException if note does not exist
	 */
	public function update(Page $page, string $userId): Page {
		$folder = $this->getFolderForUser($userId);
		$file = $this->getFileById($folder, $page->getId());
		$safeTitle = $this->sanitiseTitle($page->getTitle());

		// Rename file if title changed
		if ($safeTitle . self::SUFFIX !== $file->getName()) {
			$newFilename = self::generateFilename($folder, $safeTitle);
			try {
				$file->move($folder->getPath() . '/' . $newFilename . self::SUFFIX);
			} catch (NotPermittedException $e) {
				$err = 'Moving page ' . $page->getId() . ' (' . $newFilename . self::SUFFIX . ') to the desired target is not allowed.';
				$this->logger->error($err, ['app' => $this->appName]);
			}
			$page->setTitle($newFilename);
		}

		$file->putContent($page->getContent());

		return $this->getPage($file);
	}

	/**
	 * @param Page $page
	 * @param string $userId
	 */
	public function delete(Page $page, string $userId): void {
		$folder = $this->getFolderForUser($userId);
		$file = $this->getFileById($folder, $page->getId());
		$file->delete();
	}
}
