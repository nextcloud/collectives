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
	private int $count = 0;
	private array $fileMap = []; // Maps original file paths to new	page IDs

	public function __construct(
		private readonly PageService $pageService,
		private readonly AttachmentService $attachmentService,
		private readonly IMimeTypeDetector $mimeTypeDetector,
		private readonly IProgressReporter $progressReporter,
		private readonly Collective $collective,
		private readonly IUser $user,
	) {
	}

	public function getCount(): int {
		return $this->count;
	}

	public function importDirectory(string $directory, int $parentId): void {
		// Verify directory exists and is readable
		if (!file_exists($directory) || !is_dir($directory) || !is_readable($directory)) {
			throw new NotFoundException('Directory not accessible: ' . $directory);
		}

		if ($parentId !== 0) {
			// Also verifies that the parentId page exists
			$parentPage = $this->pageService->findByFileId($this->collective->getId(), $parentId, $this->user->getUID());
		} else {
			$parentPage = null;
		}

		$memory = memory_get_usage();
		$this->processDirectory($directory, $parentPage);
		$message = sprintf('Memory usage after importing pages: %.2fMB (peak usage: %.2fMB)',
			((float)memory_get_usage() - (float)$memory) / 1024.0 / 1024.0,
			(float)memory_get_peak_usage() / 1024.0 / 1024.0);
		$this->progressReporter->writeInfoVerbose($message);
		$memory = memory_get_usage();
		if ($this->count === 0) {
			throw new NotFoundException('No markdown files found in directory: ' . $directory);
		}

		// Third pass: rewrite relative links in all imported pages
		$this->rewriteInternalLinksAndAttachments($parentId, $directory);
		$message = sprintf('Memory usage after rewriting links and attachments: %.2fMB (+%.2fMb, peak usage: %.2fMB)',
			(float)memory_get_usage() / 1024.0 / 1024.0,
			((float)memory_get_usage() - (float)$memory) / 1024.0 / 1024.0,
			(float)memory_get_peak_usage() / 1024.0 / 1024.0);
		$this->progressReporter->writeInfoVerbose($message);
	}

	/**
	 * Recursively import Markdown files from directory
	 */
	private function processDirectory(string $directory, ?PageInfo $parentPage): void {
		// Verify directory exists and is readable
		if (!is_readable($directory)) {
			$message = sprintf('✗ Failed: %s - Directory not readable', $directory);
			$this->progressReporter->writeError($message);
			return;
		}

		$parentId = $parentPage !== null ? $parentPage->getId() : 0;
		$items = scandir($directory);
		if ($items === false) {
			throw new NotFoundException('Unable to read directory: ' . $directory);
		}

		// First pass: import Markdown files at this level
		$mdFiles = array_filter($items, static function ($item) use ($directory) {
			return is_file($directory . DIRECTORY_SEPARATOR . $item) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'md';
		});
		foreach ($mdFiles as $item) {
			$path = $directory . DIRECTORY_SEPARATOR . $item;

			try {
				[$id, $title] = $this->processFile($directory, $item, $parentPage);
				$this->fileMap[$path] = $id;
				$this->count++;
				$message = sprintf('✓ Imported #%d: %s - %s (pageId: %d)', $this->count, $path, $title, $id);
				$this->progressReporter->writeInfo($message);
			} catch (NotFoundException $e) {
				$message = sprintf('✗ Failed: %s - %s', $path, $e->getMessage());
				$this->progressReporter->writeError($message);
			}
		}

		// Second pass: import subdirectories
		$subDirs = array_filter($items, static function ($item) use ($directory) {
			return is_dir($directory . DIRECTORY_SEPARATOR . $item) && $item !== '.' && $item !== '..';
		});
		foreach ($subDirs as $item) {
			$path = $directory . DIRECTORY_SEPARATOR . $item;

			$readmeName = self::getReadmeFromDirectory($path);
			if ($readmeName !== null) {
				// Create index page from readme.md if exists
				try {
					[$id, $title] = $this->processFile($path, $readmeName, $parentPage, $item);
					$this->fileMap[$path . DIRECTORY_SEPARATOR . $readmeName] = $id;
					$this->count++;
					$message = sprintf('✓ Imported #%d: %s - %s (pageId: %d)', $this->count, $path . DIRECTORY_SEPARATOR . $readmeName, $title, $id);
					$this->progressReporter->writeInfo($message);
				} catch (NotFoundException $e) {
					$message = sprintf('✗ Failed: %s - %s', $path, $e->getMessage());
					$this->progressReporter->writeError($message);
					continue;
				}
				$indexPageInfo = $this->pageService->findByFileId($this->collective->getId(), $id, $this->user->getUID());
			} else {
				// Create new empty index page
				$indexPageInfo = $this->pageService->getOrCreate($this->collective->getId(), $parentId, $item, $this->user->getUID());
			}
			$this->processDirectory($path, $indexPageInfo);
		}
	}

	private function processFile(string $directory, string $item, ?PageInfo $parentPage, ?string $title = null): array {
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

		$content = MarkdownHelper::processCallouts($content);

		try {
			if (strtolower($title) === 'readme' && $parentId === 0) {
				// Special case: use parent directory name as title for README.md files
				$title = basename($directory);
				$parentId = $parentPage !== null ? $parentPage->getParentId() : 0;
			}
			$pageInfo = $this->pageService->createBase(
				$this->collective->getId(),
				$parentId,
				$title,
				null,
				$this->user->getUID(),
				null,
				$content,
			);
		} catch (NotFoundException|NotPermittedException $e) {
			throw new NotFoundException('Failed to create page: ' . $e->getMessage());
		}

		$id = $pageInfo->getId();
		$title = $pageInfo->getTitle();

		// Free some memory
		unset($content, $pageInfo);
		gc_collect_cycles();

		return [$id, $title];
	}

	/**
	 * Rewrite relative links in all imported pages to point to new page URLs
	 */
	private function rewriteInternalLinksAndAttachments(int $parentId, string $baseDirectory): void {
		foreach ($this->fileMap as $filePath => $pageId) {
			try {
				$pageFile = $this->pageService->getPageFile($this->collective->getId(), $pageId, $this->user->getUID());
				$content = $pageFile->getContent();
			} catch (NotFoundException|NotPermittedException $e) {
				$message = sprintf('✗ Failed: %s - Failed to read page content: %s', $filePath, $e->getMessage());
				$this->progressReporter->writeError($message);
				continue;
			}

			$updatedContent = $content;

			$links = MarkdownHelper::getLinksFromContent($content);
			$linkCount = 0;
			foreach ($links as $link) {
				$linkCount += $this->processLink($link, $parentId, $filePath, $updatedContent);
			}

			$attachments = MarkdownHelper::getImageLinksFromContent($content);
			$attachmentCount = 0;
			foreach ($attachments as $attachment) {
				$attachmentCount += $this->processAttachment($attachment, $pageFile, $filePath, $baseDirectory, $updatedContent);
			}

			$updateCount = $linkCount + $attachmentCount;
			if ($updateCount > 0) {
				NodeHelper::putContent($pageFile, $updatedContent);
				$message = sprintf('🔗 %d links and attachments updated: %s', $updateCount, $filePath);
				$this->progressReporter->writeInfo($message);
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

	/**
	 * Dokuwiki2Markdown generates links with colons instead of paths
	 * Converts `:topic:subtopic:page` to `topic/subtopic/page`
	 * Converts `:media:topic:image.png?400` to `media/topic/image.png`
	 */
	private static function getDokuwikiHref(string $href): string {
		// Remove leading ':' if existent
		if (str_starts_with($href, ':')) {
			$href = substr($href, 1);
		}

		// Remove query string (e.g. image size in attachments) if present
		$href = preg_replace('/[?#].*$/', '', $href);

		// Replace colons with directory separators to get the actual path
		return str_replace(':', DIRECTORY_SEPARATOR, $href);
	}

	private function processLink(array $link, int $parentId, string $filePath, string &$updatedContent): int {
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
		if (str_contains($sanitizedHref, ':')) {
			$dokuwikiPath = self::getDokuwikiHref($sanitizedHref);
			$candidates[] = $dokuwikiPath;

			// Add additional candidates by stripping leading path segments
			// It's an attempt to allow processing links even if a subdirectory gets imported
			$pathSegments = explode(DIRECTORY_SEPARATOR, $dokuwikiPath);
			for ($i = 1; $i < count($pathSegments); $i++) {
				$candidates[] = implode(DIRECTORY_SEPARATOR, array_slice($pathSegments, $i));
			}
		}

		// Try to find target page
		$targetPageInfo = null;
		foreach ($candidates as $candidate) {
			try {
				$targetPageInfo = $this->pageService->findByPath($this->collective->getId(), $candidate, $this->user->getUID(), $parentId);
				break;
			} catch (NotFoundException|NotPermittedException) {
			}
		}

		if ($targetPageInfo === null) {
			$message = sprintf('✗ Failed: %s - Didn\'t find target page for link %s, not updated', $filePath, $href);
			$this->progressReporter->writeErrorVerbose($message);
			return 0;
		}

		$newHref = $this->pageService->getPageLink($this->collective->getUrlPath(), $targetPageInfo);

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

	private function processAttachment(array $image, File $pageFile, string $filePath, string $baseDirectory, string &$updatedContent): int {
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
			$dokuwikiPath = self::getDokuwikiHref($sanitizedHref);
			$candidates[] = $baseDirectory . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $dokuwikiPath;
			$candidates[] = $baseDirectory . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $dokuwikiPath;

			// Add additional candidates by stripping leading path segments
			// It's an attempt to allow processing attachments even if a subdirectory gets imported
			$pathSegments = explode(DIRECTORY_SEPARATOR, $dokuwikiPath);
			for ($i = 1; $i < count($pathSegments); $i++) {
				$pathPart = implode(DIRECTORY_SEPARATOR, array_slice($pathSegments, $i));
				$candidates[] = $baseDirectory . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $pathPart;
				$candidates[] = $baseDirectory . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $pathPart;
			}
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
			$message = sprintf('✗ Failed: %s - Didn\'t find source file for attachment reference %s, not updated', $filePath, $url);
			$this->progressReporter->writeErrorVerbose($message);
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

	private static function getReadmeFromDirectory(string $path): ?string {
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
