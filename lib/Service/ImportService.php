<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\NodeHelper;
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

		// Verify the parentId page exists
		if ($parentId !== 0) {
			$this->pageService->findByFileId($collective->getId(), $parentId, $user->getUID());
		}

		if ($progressCallback === null) {
			$progressCallback = function (string $status, string $path, ?string $message) {
				// no-op
			};
		}

		$count = $this->processDirectory($directory, $collective, $parentId, $user, $progressCallback);
		if ($count === 0) {
			throw new NotFoundException('No markdown files found in directory: ' . $directory);
		}

		return $count;
	}

	/**
	 * Recursively import markdown files from directory
	 */
	private function processDirectory(string $directory, Collective $collective, int $parentId, IUser $user, callable $progressCallback): int {
		$count = 0;
		$items = scandir($directory);
		if ($items === false) {
			throw new NotFoundException('Unable to read directory: ' . $directory);
		}

		// First pass: import markdown files at this level
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = $directory . DIRECTORY_SEPARATOR . $item;

			// Verify directory exists and is readable
			if (!is_readable($directory)) {
				$progressCallback('error', $path, 'Directory not readable');
				continue;
			}

			if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'md') {
				if (!is_readable($path)) {
					$progressCallback('error', $path, 'File not readable');
					continue;
				}

				$mimeType = $this->mimeTypeDetector->detectPath($path);
				if (!in_array($mimeType, ['text/markdown', 'text/plain'], true)) {
					$progressCallback('error', $path, 'Invalid mime type: ' . $mimeType);
					continue;
				}

				$title = basename($path, '.md');
				$content = file_get_contents($path);
				if ($content === false) {
					$progressCallback('error', $path, 'Failed to read file content');
					continue;
				}

				try {
					$pageInfo = $this->pageService->create(
						$collective->getId(),
						$parentId,
						$title,
						null,
						$user->getUID(),
					);

					$pageFile = $this->pageService->getPageFile($collective->getId(), $pageInfo->getId(), $user->getUID());
					NodeHelper::putContent($pageFile, $content);
					$count++;
					$message = $pageInfo->getTitle() . ' (pageId: ' . $pageInfo->getId() . ')';
					$progressCallback('success', $path, $message);
				} catch (NotFoundException|NotPermittedException $e) {
					$progressCallback('error', $path, $e->getMessage());
				}

				continue;
			}

			if (is_dir($path)) {
				$dirPage = $this->pageService->getOrCreate($collective->getId(), $parentId, $item, $user->getUID());
				$count += $this->processDirectory($path, $collective, $dirPage->getId(), $user, $progressCallback);
				continue;
			}
		}

		return $count;
	}
}
