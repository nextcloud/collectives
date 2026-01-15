<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\MarkdownHelper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\IUser;

class ImportService {
	public function __construct(
		private PageService $pageService,
		private AttachmentService $attachmentService,
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
		$count = $this->processDirectory($directory, $collective, $parentPage, $user, false, $fileMap, $progressCallback);
		if ($count === 0) {
			throw new NotFoundException('No markdown files found in directory: ' . $directory);
		}

		// Second pass: rewrite relative links in all imported pages
		$this->rewriteInternalLinksAndAttachments($collective, $user, $fileMap, $parentId, $directory, $progressCallback);

		return $count;
	}

	/**
	 * Recursively import Markdown files from directory
	 */
	private function processDirectory(string $directory, Collective $collective, ?PageInfo $parentPage, IUser $user, bool $skipReadme, array &$fileMap, ?callable $progressCallback = null): int {
		$count = 0;
		$parentId = $parentPage !== null ? $parentPage->getId() : 0;
		$items = scandir($directory);
		if ($items === false) {
			throw new NotFoundException('Unable to read directory: ' . $directory);
		}

		// First pass: import Markdown files at this level
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			if ($skipReadme && self::getReadmeName($directory) === $item) {
				continue;
			}

			// Verify directory exists and is readable
			if (!is_readable($directory)) {
				$progressCallback('error', $directory, 'Directory not readable');
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
					$count += $this->processDirectory($path, $collective, $indexPageInfo, $user, true, $fileMap, $progressCallback);
				} else {
					$indexPageInfo = $this->pageService->getOrCreate($collective->getId(), $parentId, $item, $user->getUID());
					$count += $this->processDirectory($path, $collective, $indexPageInfo, $user, false, $fileMap, $progressCallback);
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
			$pageInfo = $this->pageService->createBase(
				$collective->getId(),
				$parentId,
				$title,
				null,
				$user->getUID(),
				null,
				$content,
			);
		} catch (NotFoundException|NotPermittedException $e) {
			throw new NotFoundException('Failed to create page: ' . $e->getMessage());
		}

		return $pageInfo;
	}

	/**
	 * Rewrite relative links in all imported pages to point to new page URLs
	 */
	private function rewriteInternalLinksAndAttachments(Collective $collective, IUser $user, array $fileMap, int $parentId, string $baseDirectory, callable $progressCallback): void {
		foreach ($fileMap as $filePath => $pageId) {
			try {
				$pageFile = $this->pageService->getPageFile($collective->getId(), $pageId, $user->getUID());
				$content = $pageFile->getContent();
			} catch (NotFoundException|NotPermittedException $e) {
				$progressCallback('error', $filePath, 'Failed to read page content: ' . $e->getMessage());
				continue;
			}

			$updatedContent = $content;

			$links = MarkdownHelper::getLinksFromContent($content);
			$linkCount = 0;
			foreach ($links as $link) {
				$linkCount += $this->processLink($link, $collective, $user, $parentId, $filePath, $updatedContent, $progressCallback);
			}

			$attachments = MarkdownHelper::getImageLinksFromContent($content);
			$attachmentCount = 0;
			foreach ($attachments as $attachment) {
				$attachmentCount += $this->processAttachment($attachment, $collective, $user, $pageFile, $filePath, $baseDirectory, $updatedContent, $progressCallback);
			}

			$updateCount = $linkCount + $attachmentCount;
			if ($updateCount > 0) {
				NodeHelper::putContent($pageFile, $updatedContent);
				$progressCallback('link_update', $filePath, "Updated $updateCount internal links and attachments");
			}
		}
	}

	private static function sanitizeHref(string $href): ?string {
		// Only process relative links (not absolute URLs or root-relative paths)
		if (!$href || str_starts_with($href, '/') || preg_match('/^[a-zA-Z]+:\/\//', $href)) {
			return null;
		}

		// Ignore mailto links
		if (str_starts_with($href, 'mailto:')) {
			return null;
		}

		// Remove fragment and query string from link
		$sanitizedHref = preg_replace('/[?#].*$/', '', $href);

		// Remove `./` prefix from relative link
		if (str_starts_with($sanitizedHref, './')) {
			$sanitizedHref = substr($sanitizedHref, 2);
		}

		return $sanitizedHref;
	}

	private function processLink(array $link, Collective $collective, IUser $user, int $parentId, string $filePath, string &$updatedContent, callable $progressCallback): int {
		$href = $link['href'];
		$sanitizedHref = self::sanitizeHref($href);
		if ($sanitizedHref === null) {
			return 0;
		}

		$candidates = [];

		// Consider link with and without .md extension
		if (str_ends_with($sanitizedHref, '.md')) {
			$candidates[] = $sanitizedHref;
			$candidates[] = substr($sanitizedHref, 0, -3); // without .md
		} else {
			$candidates[] = $sanitizedHref;
			$candidates[] = $sanitizedHref . '.md';
		}

		// E.g. Dokuwiki2Markdown generates links where pages are separated with colons
		$candidates[] = str_replace(':', DIRECTORY_SEPARATOR, $sanitizedHref);

		// Try to find target page
		$targetPageInfo = null;
		foreach ($candidates as $candidate) {
			try {
				$targetPageInfo = $this->pageService->findByPath($collective->getId(), $candidate, $user->getUID(), $parentId);
				break;
			} catch (NotFoundException|NotPermittedException) {
			}
		}

		if ($targetPageInfo === null) {
			$progressCallback('error', $filePath, "Didn't find target page for link $href, not updated");
			return 0;
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
		return 1;
	}

	private function processAttachment(array $image, Collective $collective, IUser $user, File $pageFile, string $filePath, string $baseDirectory, string &$updatedContent, callable $progressCallback): int {
		$url = $image['url'];
		if (!$url) {
			return 0;
		}
		$sanitizedHref = self::sanitizeHref($url);
		if ($sanitizedHref === null) {
			return 0;
		}

		$candidates = [];
		$candidates[] = $baseDirectory . DIRECTORY_SEPARATOR . $sanitizedHref;

		if (str_contains($sanitizedHref, ':')) {
			// Dokuwiki2Markdown generates links where attachments are separated with colons

			// Remove leading ':' if existent
			if (str_starts_with($sanitizedHref, ':')) {
				$sanitizedHref = substr($sanitizedHref, 1);
			}

			// Convert :directory:attachment.png?400 to directory/attachment.png
			$dokuwikiPath = str_replace(':', DIRECTORY_SEPARATOR, $sanitizedHref);
			// Remove query string (image size) if present
			$dokuwikiPath = preg_replace('/[?#].*$/', '', $dokuwikiPath);

			$candidates[] = $baseDirectory . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $dokuwikiPath;
			$candidates[] = $baseDirectory . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $dokuwikiPath;
		}

		// Try to find linked attachment
		$targetAttachment = null;
		foreach ($candidates as $candidate) {
			$realPath = realpath($candidate);
			if ($realPath && is_file($realPath) && is_readable($realPath)) {
				$targetAttachment = $realPath;
				break;
			}
		}


		if ($targetAttachment === null) {
			$progressCallback('error', $filePath, "Didn't find source file for attachment reference $url, not updated");
			return 0;
		}

		$newUrl = $this->attachmentService->putAttachment(
			$pageFile,
			basename($targetAttachment),
			file_get_contents($targetAttachment) ?: '',
		);

		// Replace the attachment reference in content
		$oldLink = '](' . $url . ')';
		$newLink = '](' . $newUrl . ')';
		$updatedContent = str_replace($oldLink, $newLink, $updatedContent);
		return 1;
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
