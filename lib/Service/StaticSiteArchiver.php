<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use FilesystemIterator;
use Generator;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\Lock\LockedException;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Stores the files of a static site as tar.gz archive in appdata.
 */
class StaticSiteArchiver {
	private const APPDATA_FOLDER = 'static_sites';
	private const ARCHIVE_SUFFIX = '.tar.gz';
	// Path limits of the ustar header written by PharData (ustar is the standard tar format), in bytes
	private const TAR_NAME_MAX_LENGTH = 100;
	private const TAR_PREFIX_MAX_LENGTH = 155;

	public function __construct(
		private readonly IAppData $appData,
		private readonly ITempManager $tempManager,
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * Build the archive and replace a previously stored archive of the static site.
	 *
	 * PharData needs a local file, but app data is only accessible via streams
	 * (and may be stored on object storage), so the archive is built in a temporary
	 * folder first and then copied to app data. A previously stored archive is only
	 * replaced once the new one was built successfully.
	 *
	 * @param array<string, File> $files Files mapped to their relative path in the archive
	 *
	 * @throws UnprocessableEntityException No files given or a path is too long for the archive
	 * @throws ServiceException
	 */
	public function store(string $staticSiteId, array $files): void {
		if ($files === []) {
			throw new UnprocessableEntityException($this->l10n->t('There is no content to publish.'));
		}

		foreach (array_keys($files) as $path) {
			$this->validateArchivePath((string)$path);
			$this->validateArchivePathLength((string)$path);
		}

		$tempFolder = $this->tempManager->getTemporaryFolder();
		if ($tempFolder === false) {
			throw new ServiceException('Failed to create temporary folder for static site archive');
		}

		try {
			$archivePath = $this->buildArchive($tempFolder, $files);
			$this->writeToAppData($staticSiteId, $archivePath);
		} finally {
			// ITempManager only removes temporary files in a background job, archives may be too large to wait for the job
			$this->removeDirectory($tempFolder);
		}
	}

	/**
	 * @throws ServiceException
	 */
	public function delete(string $staticSiteId): void {
		try {
			$folder = $this->appData->getFolder(self::APPDATA_FOLDER);
			$name = $staticSiteId . self::ARCHIVE_SUFFIX;
			if ($folder->fileExists($name)) {
				$folder->getFile($name)->delete();
			}
		} catch (FilesNotFoundException) {
			// No archives stored yet
		} catch (FilesNotPermittedException $e) {
			throw new ServiceException('Failed to delete static site archive: ' . $e->getMessage(), 0, $e);
		}
	}

	/**
	 * Reject paths that would point outside the archive root when extracted.
	 *
	 * @throws ServiceException
	 */
	private function validateArchivePath(string $path): void {
		$segments = explode('/', $path);
		if ($path === ''
			|| str_starts_with($path, '/')
			|| str_contains($path, '\\')
			|| in_array('', $segments, true)
			|| in_array('.', $segments, true)
			|| in_array('..', $segments, true)) {
			throw new ServiceException('Invalid path for static site archive: ' . $path);
		}
	}

	/**
	 * Mirrors the check of PharData, which splits long paths at a slash into prefix and name.
	 *
	 * @throws UnprocessableEntityException
	 */
	private function validateArchivePathLength(string $path): void {
		$length = strlen($path);
		if ($length <= self::TAR_NAME_MAX_LENGTH) {
			return;
		}

		$boundary = $length <= self::TAR_PREFIX_MAX_LENGTH + 1 + self::TAR_NAME_MAX_LENGTH
			? strpos($path, '/', $length - self::TAR_NAME_MAX_LENGTH - 1)
			: false;
		if ($boundary === false || $boundary > self::TAR_PREFIX_MAX_LENGTH) {
			throw new UnprocessableEntityException($this->l10n->t(
				'The path "%s" is too long for the website. Please shorten the titles of the pages in this path or nest them less deeply.',
				[$path],
			));
		}
	}

	/**
	 * Source files are copied to flat local names, so the archive paths, set by the user, never get used as local paths in the filesystem.
	 *
	 * @param array<string, File> $files
	 *
	 * @return string Local path of the tar.gz archive
	 *
	 * @throws ServiceException
	 */
	private function buildArchive(string $tempFolder, array $files): string {
		$filesFolder = $tempFolder . '/files';
		if (!mkdir($filesFolder)) {
			throw new ServiceException('Failed to create temporary folder for static site files');
		}

		$localPaths = [];
		$index = 0;
		foreach ($files as $path => $file) {
			$localPath = $filesFolder . '/' . $index++;
			$this->copyToLocal($file, $localPath);
			$localPaths[$path] = $localPath;
		}

		try {
			// PharData detects the archive format from the file extension
			$tar = new PharData($tempFolder . '/site.tar');
			// PharData rewrites the whole archive on every addFile(), so all files are added in one go.
			// The generator casts keys to strings, as numeric paths become integer array keys and would get rejected by PharData.
			$tar->buildFromIterator((static function () use ($localPaths): Generator {
				foreach ($localPaths as $path => $localPath) {
					yield (string)$path => $localPath;
				}
			})());
			// Creates site.tar.gz next to uncompressed site.tar
			$tar->compress(Phar::GZ);
		} catch (\UnexpectedValueException|\BadMethodCallException|\PharException $e) {
			throw new ServiceException('Failed to build static site archive: ' . $e->getMessage(), 0, $e);
		}

		$archivePath = $tempFolder . '/site' . self::ARCHIVE_SUFFIX;
		if (!is_file($archivePath)) {
			throw new ServiceException('PharData did not create the compressed static site archive');
		}
		return $archivePath;
	}

	/**
	 * @throws ServiceException
	 */
	private function copyToLocal(File $file, string $localPath): void {
		try {
			$source = $file->fopen('r');
		} catch (FilesNotPermittedException $e) {
			throw new ServiceException('Failed to read file ' . $file->getPath() . ': ' . $e->getMessage(), 0, $e);
		}
		if ($source === false) {
			throw new ServiceException('Failed to read file ' . $file->getPath());
		}

		$target = fopen($localPath, 'wb');
		try {
			if ($target === false || stream_copy_to_stream($source, $target) === false) {
				throw new ServiceException('Failed to copy file ' . $file->getPath());
			}
		} finally {
			fclose($source);
			if ($target !== false) {
				fclose($target);
			}
		}
	}

	/**
	 * @throws ServiceException
	 */
	private function writeToAppData(string $staticSiteId, string $archivePath): void {
		$handle = fopen($archivePath, 'rb');
		if ($handle === false) {
			throw new ServiceException('Failed to open static site archive');
		}

		try {
			$folder = $this->getAppDataFolder();
			$name = $staticSiteId . self::ARCHIVE_SUFFIX;
			if ($folder->fileExists($name)) {
				$folder->getFile($name)->putContent($handle);
			} else {
				$folder->newFile($name, $handle);
			}
		} catch (FilesNotFoundException|FilesNotPermittedException|GenericFileException|LockedException $e) {
			throw new ServiceException('Failed to store static site archive: ' . $e->getMessage(), 0, $e);
		} finally {
			/** @psalm-suppress RedundantCondition Storage backends may close the stream after writing */
			if (is_resource($handle)) {
				fclose($handle);
			}
		}
	}

	/**
	 * ISimpleFolder::getOrCreateFolder() is only available from Nextcloud 35 on.
	 *
	 * @throws FilesNotPermittedException
	 */
	private function getAppDataFolder(): ISimpleFolder {
		try {
			return $this->appData->getFolder(self::APPDATA_FOLDER);
		} catch (FilesNotFoundException) {
			return $this->appData->newFolder(self::APPDATA_FOLDER);
		}
	}

	private function removeDirectory(string $path): void {
		if (!is_dir($path)) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);
		foreach ($iterator as $item) {
			$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}
		rmdir($path);
	}
}
