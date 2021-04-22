<?php

namespace OCA\Collectives\Fs;

use OCA\Collectives\Service\PageDoesNotExistException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IDBConnection;
use OCP\IL10N;

class NodeHelper {
	/** @var IDBConnection */
	private $db;

	/** @var IL10N */
	private $l10n;

	/** @var bool */
	private $db4ByteSupport;

	public function __construct(
		IDBConnection $db,
		IL10N $l10n) {
		$this->db = $db;
		$this->l10n = $l10n;
		$this->db4ByteSupport = $this->db->supports4ByteText();
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
	 * @param string $suffix
	 *
	 * @return string
	 */
	public static function generateFilename(Folder $folder, string $filename, string $suffix = ''): string {
		$path = $filename . $suffix;
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

		return self::generateFilename($folder, $newFilename, $suffix);
	}

	/**
	 * Removes or replaces characters that are illegal in a file or folder name on some operating systems.
	 * Most code copied from `Service::NoteUtil::sanitisePath()` from Notes App.
	 *
	 * @param string $name
	 * @param string $default
	 *
	 * @return string
	 */
	public function sanitiseFilename(string $name, string $default = 'New File'): string {
		// replace '/' with '-' to prevent directory traversal
		// replacing instead of stripping seems the better tradeoff here
		$name = str_replace('/', '-', $name);

		// remove characters which are illegal on Windows (includes illegal characters on Unix/Linux)
		// see also \OC\Files\Storage\Common::verifyPosixPath(...)
		/** @noinspection CascadeStringReplacementInspection */
		$name = str_replace(['*', '|', '\\', ':', '"', '<', '>', '?'], '', $name);

		// if mysql doesn't support 4byte UTF-8, then remove those characters
		// see \OC\Files\Storage\Common::verifyPath(...)
		if (!$this->db4ByteSupport) {
			$name = preg_replace('%(?:
                \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
              | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
              | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
              )%xs', '', $name);
		}

		// remove leading spaces or dots to prevent hidden files
		$name = preg_replace('/^[\. ]+/mu', '', $name);

		// remove leading and trailing spaces
		$name = trim($name);

		if (empty($name)) {
			$name = $this->l10n->t($default);
		}

		return $name;
	}
}
