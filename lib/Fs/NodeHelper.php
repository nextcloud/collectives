<?php

namespace OCA\Wiki\Fs;

use OCA\Pages\Service\PagesFolderException;
use OCA\Wiki\Service\PageDoesNotExistException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;
use OCP\IL10N;

class NodeHelper {
	private const SUFFIX = '.md';
	private const WIKI_FOLDER = 'Wiki';

	private $db;
	private $l10n;
	private $root;

	public function __construct(
		IDBConnection $db,
		IL10N $l10n,
		IRootFolder $root) {
		$this->db = $db;
		$this->l10n = $l10n;
		$this->root = $root;
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function getFolderForUser(string $userId): Folder {
		$path = '/' . $userId . '/files/' . self::WIKI_FOLDER;
		return $this->getOrCreateFolder($path);
	}

	/**
	 * @param string $path
	 *
	 * @return Folder
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function getOrCreateFolder(string $path): Folder {
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
	public function getFileById(Folder $folder, int $id): File {
		$file = $folder->getById($id);

		if (count($file) <= 0 || !($file[0] instanceof File)) {
			throw new PageDoesNotExistException('page does not exist');
		}
		return $file[0];
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
	 * Removes or replaces characters that are illegal in a file or folder name on some operating systems.
	 * Most code copied from `Service::NoteUtil::sanitisePath()` from Notes App.
	 *
	 * @param string $title
	 *
	 * @return string
	 */
	public function sanitiseFilename(string $title): string {
		// replace '/' with '-' to prevent directory traversal
		// replacing instead of stripping seems the better tradeoff here
		$title = str_replace('/', '-', $title);

		// remove characters which are illegal on Windows (includes illegal characters on Unix/Linux)
		// see also \OC\Files\Storage\Common::verifyPosixPath(...)
		/** @noinspection CascadeStringReplacementInspection */
		$title = str_replace(['*', '|', '\\', ':', '"', '<', '>', '?'], '', $title);

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

		// remove leading and trailing spaces
		$title = trim($title);

		if (empty($title)) {
			$title = $this->l10n->t('New Page');
		}

		return $title;
	}
}
