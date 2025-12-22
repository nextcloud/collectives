<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Fs\MarkdownHelper;
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

		$fileMap = [];
		$count = $this->processDirectory($directory, $collective, $parentPage, $user, false, $progressCallback, $fileMap);
		if ($count === 0) {
			throw new NotFoundException('No markdown files found in directory: ' . $directory);
		}

		// Second pass: rewrite relative links in all imported pages
		$this->rewriteInternalLinks($collective, $user, $fileMap, $parentId, $progressCallback);

		return $count;
	}

	/**
	 * Recursively import markdown files from directory
	 */
	private function processDirectory(string $directory, Collective $collective, ?PageInfo $parentPage, IUser $user, bool $skipReadme, ?callable $progressCallback = null, array &$fileMap = []): int {
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
					$fileMap[$path] = $pageInfo->getId();
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
						$fileMap[$path . DIRECTORY_SEPARATOR . $readmeName] = $indexPageInfo->getId();
						$count++;
						$message = $indexPageInfo->getTitle() . ' (pageId: ' . $indexPageInfo->getId() . ')';
						$progressCallback('success', $path . DIRECTORY_SEPARATOR . $readmeName, $message);
					} catch (NotFoundException $e) {
						$progressCallback('error', $path . DIRECTORY_SEPARATOR . $readmeName, $e->getMessage());
						continue;
					}
					$count += $this->processDirectory($path, $collective, $indexPageInfo, $user, true, $progressCallback, $fileMap);
				} else {
					$indexPageInfo = $this->pageService->getOrCreate($collective->getId(), $parentId, $item, $user->getUID());
					$count += $this->processDirectory($path, $collective, $indexPageInfo, $user, false, $progressCallback, $fileMap);
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

	/**
	 * Rewrite relative links in all imported pages to point to new page URLs
	 */
	private function rewriteInternalLinks(Collective $collective, IUser $user, array $fileMap, int $parentId, callable $progressCallback): void {
		foreach ($fileMap as $filePath => $pageId) {
			try {
				$pageFile = $this->pageService->getPageFile($collective->getId(), $pageId, $user->getUID());
				$content = $pageFile->getContent();
			} catch (NotFoundException|NotPermittedException $e) {
				$progressCallback('error', $filePath, 'Failed to read page content: ' . $e->getMessage());
				continue;
			}

			$links = MarkdownHelper::getLinksFromContent($content);
			if (empty($links)) {
				continue;
			}

			$updatedContent = $content;
			$linkCount = 0;

			foreach ($links as $link) {
				$href = $link['href'];

				// Only process relative links (not absolute URLs or root-relative paths)
				if (!$href || str_starts_with($href, '/') || preg_match('/^[a-zA-Z]+:\/\//', $href)) {
					continue;
				}

				// Remove fragment and query string from link
				$alteredHref = preg_replace('/[?#].*$/', '', $href);

				// Remove `./` prefix from relative link
				if (str_starts_with($alteredHref, './')) {
					$alteredHref = substr($alteredHref, 2);
				}

				$candidates = [];

				// Consider link with and without .md extension
				if (str_ends_with($alteredHref, '.md')) {
					$candidates[] = $alteredHref;
					$candidates[] = substr($alteredHref, 0, -3); // without .md
				} else {
					$candidates[] = $alteredHref;
					$candidates[] = $alteredHref . '.md';
				}

				// E.g. Dokuwiki2Markdown generates links where pages are separated with colons
				$candidates[] = str_replace(':', DIRECTORY_SEPARATOR, $alteredHref);

				// Try to find target page
				$targetPageInfo = null;
				foreach ($candidates as $candidate) {
					try {
						$targetPageInfo = $this->pageService->findByPath($collective->getId(), $candidate, $user->getUID(), $parentId)
							?? $this->pageService->findByPath($collective->getId(), $candidate, $user->getUID(), $parentId);
						break;
					} catch (NotFoundException|NotPermittedException) {
					}
				}

				if ($targetPageInfo === null) {
					$progressCallback('error', $filePath, "Didn't find target page for link $href, not updated");
					continue;
				}

				$newHref = $this->pageService->getPageLink($collective->getUrlPath(), $targetPageInfo);

				// Preserve fragment if present
				if (preg_match('/#(.+)$/', $href, $matches)) {
					$newHref .= '#' . $matches[1];
				}

				// Replace the link in content
				$oldLink = '](' . $href . ')';
				$newLink = '](' . $newHref . ')';
				$updatedContent = str_replace($oldLink, $newLink, $updatedContent);
				$linkCount++;
			}

			if ($linkCount > 0) {
				NodeHelper::putContent($pageFile, $updatedContent);
				$progressCallback('link_update', $filePath, "Updated $linkCount internal link(s)");
			}
		}
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
