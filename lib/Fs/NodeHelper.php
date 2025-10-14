<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Fs;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

class NodeHelper {
	private bool $db4ByteSupport;

	public function __construct(
		IDBConnection $db,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
		$this->db4ByteSupport = $db->supports4ByteText();
	}

	/**
	 * @throws NotFoundException
	 */
	public function getFileById(Folder $folder, int $id): File {
		$file = $folder->getById($id);

		if (count($file) <= 0 || !($file[0] instanceof File)) {
			throw new NotFoundException('File not found: ' . $id);
		}
		return $file[0];
	}

	public static function generateFilename(Folder $folder, string $filename, string $suffix = ''): string {
		$path = $filename . $suffix;
		if (!$folder->nodeExists($filename) && !$folder->nodeExists($path)) {
			return $filename;
		}

		// Append ' (#)' if filename conflict ('#' starting at 2 and incremented if necessary)
		$match = preg_match('/\((?P<id>\d+)\)$/u', $filename, $matches);
		if ($match) {
			$newId = ((int)$matches['id']) + 1;
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

		// remove leading+trailing spaces and dots to prevent hidden files
		$name = trim($name, ' .');

		if ($name === '') {
			$name = $this->l10n->t($default);
		}

		return $name;
	}

	/**
	 * Most of the logic copied from `lib/Service/Note.php` from the Notes app
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getContent(File $file): string {
		try {
			$content = $file->getContent();
		} catch (FilesNotPermittedException|LockedException $e) {
			throw new NotPermittedException('Failed to read file content for ' . $file->getPath(), 0, $e);
		} catch (GenericFileException $e) {
			throw new NotFoundException('Failed to read file content for ' . $file->getPath(), 0, $e);
		}

		// blank files return false when using object storage as primary storage
		try {
			if ($file->getSize() === 0) {
				$content = '';
			}
		} catch (InvalidPathException|FilesNotFoundException $e) {
			throw new NotFoundException('Failed to read file content for ' . $file->getPath(), 0, $e);
		}

		if (!is_string($content)) {
			throw new NotFoundException('Failed to read file content for ' . $file->getPath());
		}

		if (!mb_check_encoding($content, 'UTF-8')) {
			$convertedContent = mb_convert_encoding($content, 'UTF-8');
			if ($convertedContent !== false) {
				$content = $convertedContent;
			} else {
				$this->logger->warning('Failed convert file content to UTF-8 for ' . $file->getPath());
			}
		}

		return $content;
	}

	public static function getTitleFromFile(File $file): string {
		if (self::isLandingPage($file)) {
			return '';
		}

		if (self::isIndexPage($file)) {
			$dirName = dirname($file->getInternalPath());
			$dirName = $dirName === '.' ? '' : $dirName;
			return basename($dirName);
		}

		return basename($file->getName(), PageInfo::SUFFIX);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public static function putContent(File $file, string $content): void {
		if (!mb_check_encoding($content, 'UTF-8')) {
			$content = mb_convert_encoding($content, 'UTF-8');
		}

		try {
			$file->putContent($content);
		} catch (FilesNotPermittedException|LockedException $e) {
			throw new NotPermittedException('Failed to write file content for ' . $file->getPath(), 0, $e);
		}
	}

	public static function isPageFilename(string $name): bool {
		$length = strlen(PageInfo::SUFFIX);
		return (substr($name, -$length) === PageInfo::SUFFIX);
	}

	public static function isPage(File $file): bool {
		return self::isPageFilename($file->getName());
	}

	public static function isLandingPage(File $file): bool {
		$internalPath = $file->getInternalPath() ?: '';
		return ($internalPath === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX)
			|| preg_match('/^appdata_\w+\/collectives\/\d+\/' . PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX . '$/', $internalPath);
	}

	public static function isIndexPage(File $file): bool {
		$name = $file->getName();
		return ($name === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
	}

	public static function indexPageHasOtherContent(File $file): bool {
		try {
			foreach ($file->getParent()->getDirectoryListing() as $node) {
				// True if page and not index page
				if ($node instanceof File
					&& !self::isIndexPage($node)
					&& self::isPage($node)) {
					return true;
				}
				// True if not index page or corresponding attachments folder
				if (!($node instanceof File && self::isIndexPage($node))
					&& $node->getName() !== '.attachments.' . $file->getId()) {
					return true;
				}
			}
		} catch (FilesNotFoundException) {
		}

		return false;
	}

	public static function folderHasSubPages(Folder $folder): bool {
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof File
					&& self::isPage($node)
					&& !self::isIndexPage($node)) {
					return true;
				}

				if ($node instanceof Folder) {
					return self::folderHasSubPages($node);
				}
			}
		} catch (FilesNotFoundException) {
		}

		return false;
	}

	public static function folderHasSubPage(Folder $folder, string $title): int {
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof File
					&& strcmp($node->getName(), $title . PageInfo::SUFFIX) === 0) {
					return 1;
				}

				if ($node instanceof Folder
					&& strcmp($node->getName(), $title) === 0
					&& $node->nodeExists(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX)) {
					return 2;
				}
			}
		} catch (FilesNotFoundException) {
		}

		return 0;
	}
}
