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

	public function importDirectory(string $directory, Collective $collective, int $parentId, IUser $user): array {
		// Verify directory exists and is readable
		if (!file_exists($directory) || !is_dir($directory) || !is_readable($directory)) {
			throw new NotFoundException('Directory not accessible: ' . $directory);
		}

		// Verify the parentId page exists
		if ($parentId !== 0) {
			$this->pageService->findByFileId($collective->getId(), $parentId, $user->getUID());
		}

		$stats = ['success' => 0, 'failure' => 0, 'errors' => []];
		$this->processDirectory($directory, $collective, $parentId, $user, $stats);

		if ($stats['success'] === 0 && $stats['failure'] === 0) {
			throw new NotFoundException('No markdown files found in directory: ' . $directory);
		}

		return [$stats['success'], $stats['failure'], $stats['errors']];
	}

	/**
	 * Recursively import markdown files from directory
	 */
	private function processDirectory(string $directory, Collective $collective, int $parentId, IUser $user, array &$stats): void {
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
				$stats['errors'][] = $path;
				$stats['failure']++;
				continue;
			}

			if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'md') {
				if (!is_readable($path)) {
					$stats['errors'][] = $path;
					$stats['failure']++;
					continue;
				}

				$mimeType = $this->mimeTypeDetector->detectPath($path);
				if (!in_array($mimeType, ['text/markdown', 'text/plain'], true)) {
					$stats['errors'][] = $path;
					$stats['failure']++;
					continue;
				}

				$title = basename($path, '.md');
				$content = file_get_contents($path);
				if ($content === false) {
					$stats['errors'][] = $path;
					$stats['failure']++;
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
					$stats['success']++;
				} catch (NotFoundException|NotPermittedException $e) {
					$stats['errors'][] = $path;
					$stats['failure']++;
				}

				continue;
			}

			if (is_dir($path)) {
				$dirPage = $this->pageService->getOrCreate($collective->getId(), $parentId, $item, $user->getUID());
				$this->processDirectory($path, $collective, $dirPage->getId(), $user, $stats);
				continue;
			}
		}
	}
}
