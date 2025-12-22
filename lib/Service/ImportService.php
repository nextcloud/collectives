<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCP\Files\IMimeTypeDetector;
use OCP\IUser;

class ImportService {
	public function __construct(
		private PageService $pageService,
		private IMimeTypeDetector $mimeTypeDetector,
	) {
	}

	public function importDirectory(string $directory, Collective $collective, int $parentId, IUser $user, ?callable $progressCallback = null): int {
		// Verify directory exists and is readable
		if (!file_exists($directory) || !is_dir($directory) || !is_readable($directory)) {
			throw new NotFoundException('Directory not accessible: ' . $directory);
		}

		if ($parentId !== 0) {
			// Also verifies that the parentId page exists
			$parentPage = $this->pageService->findByFileId($collective->getId(), $parentId, $user->getUID());
		} else {
			$parentPage = null;
		}

		if ($progressCallback === null) {
			$progressCallback = function (string $status, string $path, ?string $message) {
				// no-op
			};
		}

		$count = $this->processDirectory($directory, $collective, $parentPage, $user, false, $progressCallback);
		if ($count === 0) {
			throw new NotFoundException('No markdown files found in directory: ' . $directory);
		}

		return $count;
	}

	/**
	 * Recursively import markdown files from directory
	 */
	private function processDirectory(string $directory, Collective $collective, ?PageInfo $parentPage, IUser $user, bool $skipReadme, ?callable $progressCallback = null): int {
		$count = 0;
		$parentId = $parentPage !== null ? $parentPage->getId() : 0;
		$items = scandir($directory);
		if ($items === false) {
			throw new NotFoundException('Unable to read directory: ' . $directory);
		}

		// First pass: import markdown files at this level
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			if ($skipReadme && self::getReadmeName($directory) === $item) {
				continue;
			}

			// Verify directory exists and is readable
			if (!is_readable($directory)) {
				$progressCallback('error', $path, 'Directory not readable');
				continue;
			}

			$path = $directory . DIRECTORY_SEPARATOR . $item;
			$title = basename($path, '.md');

			if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'md') {
				try {
					$pageInfo = $this->processFile($directory, $item, $collective, $user, $parentPage);
					$count++;
					$message = $pageInfo->getTitle() . ' (pageId: ' . $pageInfo->getId() . ')';
					$progressCallback('success', $path, $message);
				} catch (NotFoundException $e) {
					$progressCallback('error', $path, $e->getMessage());
				}
			} elseif (is_dir($path)) {
				// Create index page if directory contains a README.md
				$readmeName = self::getReadmeName($path);
				if ($readmeName !== null) {
					try {
						$indexPageInfo = $this->processFile($path, $readmeName, $collective, $user, $parentPage, $title);
						$count++;
						$message = $indexPageInfo->getTitle() . ' (pageId: ' . $indexPageInfo->getId() . ')';
						$progressCallback('success', $path . DIRECTORY_SEPARATOR . $readmeName, $message);
					} catch (NotFoundException $e) {
						$progressCallback('error', $path . DIRECTORY_SEPARATOR . $readmeName, $e->getMessage());
						continue;
					}
					$count += $this->processDirectory($path, $collective, $indexPageInfo, $user, true, $progressCallback);
				} else {
					$indexPageInfo = $this->pageService->getOrCreate($collective->getId(), $parentId, $item, $user->getUID());
					$count += $this->processDirectory($path, $collective, $indexPageInfo, $user, false, $progressCallback);
				}
			}
		}

		return $count;
	}

	private function processFile(string $directory, string $item, Collective $collective, IUser $user, ?PageInfo $parentPage, ?string $title = null): PageInfo {
		$path = $directory . DIRECTORY_SEPARATOR . $item;
		$parentId = $parentPage !== null ? $parentPage->getId() : 0;
		$title = $title ?? basename($path, '.md');
		if (!is_readable($path)) {
			throw new NotFoundException('File not readable');
		}

		$mimeType = $this->mimeTypeDetector->detectPath($path);
		if (!in_array($mimeType, ['text/markdown', 'text/plain'], true)) {
			throw new NotFoundException('Invalid mime type: ' . $mimeType);
		}

		$content = file_get_contents($path);
		if ($content === false) {
			throw new NotFoundException('Failed to read file content');
		}

		try {
			if (strtolower($title) === 'readme' && $parentId === 0) {
				// Special case: use parent directory name as title for README.md files
				$title = basename($directory);
				$parentId = $parentPage !== null ? $parentPage->getParentId() : 0;
			}
			$pageInfo = $this->pageService->create(
				$collective->getId(),
				$parentId,
				$title,
				null,
				$user->getUID(),
			);

			$pageFile = $this->pageService->getPageFile($collective->getId(), $pageInfo->getId(), $user->getUID());
			NodeHelper::putContent($pageFile, $content);
		} catch (NotFoundException|NotPermittedException $e) {
			throw new NotFoundException('Failed to create page: ' . $e->getMessage());
		}

		return $pageInfo;

	}

	private static function getReadmeName(string $path): ?string {
		$items = scandir($path);
		if ($items === false) {
			return null;
		}

		foreach ($items as $item) {
			if (strtolower($item) === 'readme.md') {
				return $item;
			}
		}

		return null;
	}
}
