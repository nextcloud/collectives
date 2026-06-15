<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class CollectiveExportService {
	private const SKELETON_DIR = __DIR__ . '/Hugo/skeleton';

	public function __construct(
		private readonly CollectiveService $collectiveService,
		private readonly PageService $pageService,
		private readonly NodeHelper $nodeHelper,
		private readonly HugoService $hugoService,
		private readonly ITempManager $tempManager,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Render a page (and its descendants) to a static HTML site with Hugo and return it as a zip.
	 *
	 * @return array{0: string, 1: string} Path to zip file and suggested download filename
	 *
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function createStaticSiteZip(int $collectiveId, int $pageId, string $userId): array {
		$collective = $this->collectiveService->getCollective($collectiveId, $userId);
		$pageInfo = $this->pageService->find($collectiveId, $pageId, $userId);
		$pageFile = $this->pageService->getPageFile($collectiveId, $pageId, $userId);
		$collectiveFolder = $this->pageService->getCollectiveFolder($collectiveId, $userId);

		$siteDir = $this->tempManager->getTemporaryFolder('collectives-hugo');
		if ($siteDir === false) {
			throw new NotPermittedException('Failed to create temporary directory for collective export');
		}
		$siteDir = rtrim($siteDir, '/');

		$title = $pageInfo->getTitle() !== ''
			? $pageInfo->getTitle()
			: $collective->getName();

		$this->scaffoldSite($siteDir, $title);
		$contentDir = $siteDir . '/content';

		if (NodeHelper::isLandingPage($pageFile)) {
			$this->writeBranch($collectiveFolder, $contentDir, $collective->getName());
		} elseif (NodeHelper::isIndexPage($pageFile)) {
			$folder = $pageFile->getParent();
			if (!($folder instanceof Folder)) {
				throw new NotFoundException('Failed to get folder for page ' . $pageId);
			}
			$this->writeBranch($folder, $contentDir, $folder->getName());
		} else {
			$this->writeSinglePage($pageFile, $contentDir, $title);
		}

		$publicDir = $this->hugoService->build($siteDir);

		$zipPath = $this->tempManager->getTemporaryFile('zip');
		if ($zipPath === false) {
			throw new NotPermittedException('Failed to create temporary file for collective export');
		}
		$this->zipDirectory($publicDir, $zipPath);

		$filename = $this->nodeHelper->sanitiseFilename($title, 'page') . '.zip';

		return [$zipPath, $filename];
	}

	/**
	 * Copy the embedded Hugo layouts and write a generated config for the given site title.
	 *
	 * @throws NotPermittedException
	 */
	private function scaffoldSite(string $siteDir, string $title): void {
		$this->copyDirectory(self::SKELETON_DIR, $siteDir);
		$this->ensureDir($siteDir . '/content');

		$config = "baseURL = '/'\n"
			. "languageCode = 'en'\n"
			. 'title = ' . $this->tomlString($title) . "\n"
			. 'cacheDir = ' . $this->tomlString($siteDir . '/.hugo_cache') . "\n"
			. "relativeURLs = true\n"
			. "canonifyURLs = false\n"
			. "enableEmoji = true\n"
			. "disableKinds = ['taxonomy', 'term', 'rss', 'sitemap']\n"
			. "[markup]\n"
			. "  [markup.goldmark]\n"
			. "    [markup.goldmark.renderer]\n"
			. "      unsafe = true\n"
			. "  [markup.tableOfContents]\n"
			. "    startLevel = 2\n"
			. "    endLevel = 4\n";

		if (file_put_contents($siteDir . '/hugo.toml', $config) === false) {
			throw new NotPermittedException('Failed to write hugo configuration for collective export');
		}
	}

	/**
	 * Write a folder (collective root or a subpage folder) as a Hugo content branch.
	 *
	 * The folder's `Readme.md` becomes `_index.md`; leaf pages become page bundles and
	 * subfolders are recursed into. Attachments are copied next to the page that owns them.
	 *
	 * @throws NotPermittedException
	 */
	private function writeBranch(Folder $folder, string $destDir, string $title): void {
		$this->ensureDir($destDir);

		try {
			$nodes = $folder->getDirectoryListing();
		} catch (FilesNotFoundException $e) {
			$this->logger->debug('Collective export: failed to list folder ' . $folder->getPath(), ['exception' => $e]);
			$this->writeMarkdown($destDir . '/_index.md', $title, '');
			return;
		}

		$indexFile = null;
		foreach ($nodes as $node) {
			if ($node instanceof File && NodeHelper::isIndexPage($node)) {
				$indexFile = $node;
				break;
			}
		}

		if ($indexFile instanceof File) {
			$this->writeMarkdown($destDir . '/_index.md', $title, $this->readContent($indexFile));
			$this->copyAttachments($folder, $indexFile->getId(), $destDir);
		} else {
			$this->writeMarkdown($destDir . '/_index.md', $title, '');
		}

		// Folder names of subpage trees, so we don't also emit a colliding leaf bundle.
		$subFolderNames = [];
		foreach ($nodes as $node) {
			if ($node instanceof Folder && !$this->shouldSkipNode($node)) {
				$subFolderNames[$node->getName()] = true;
			}
		}

		$usedSlugs = ['_index' => true];
		foreach ($nodes as $node) {
			if ($this->shouldSkipNode($node)) {
				continue;
			}

			if ($node instanceof File) {
				if (!NodeHelper::isPage($node) || NodeHelper::isIndexPage($node)) {
					continue;
				}
				$base = basename($node->getName(), PageInfo::SUFFIX);
				if (isset($subFolderNames[$base])) {
					// Subpage tree folder of the same name handles this page.
					continue;
				}
				$slug = $this->uniqueSlug($base, $usedSlugs);
				$bundleDir = $destDir . '/' . $slug;
				$this->ensureDir($bundleDir);
				$this->writeMarkdown($bundleDir . '/index.md', $base, $this->readContent($node));
				$this->copyAttachments($node->getParent(), $node->getId(), $bundleDir);
				continue;
			}

			if ($node instanceof Folder) {
				$slug = $this->uniqueSlug($node->getName(), $usedSlugs);
				$this->writeBranch($node, $destDir . '/' . $slug, $node->getName());
			}
		}
	}

	/**
	 * Write a single leaf page as the whole site root (plus its subpage tree, if any).
	 *
	 * @throws NotPermittedException
	 */
	private function writeSinglePage(File $pageFile, string $contentDir, string $title): void {
		$this->ensureDir($contentDir);
		$this->writeMarkdown($contentDir . '/_index.md', $title, $this->readContent($pageFile));

		$parent = $pageFile->getParent();
		if (!($parent instanceof Folder)) {
			return;
		}

		$this->copyAttachments($parent, $pageFile->getId(), $contentDir);

		// A leaf page can have a sibling folder named after it holding subpages.
		$base = basename($pageFile->getName(), PageInfo::SUFFIX);
		if ($parent->nodeExists($base)) {
			$node = $parent->get($base);
			if ($node instanceof Folder) {
				$usedSlugs = ['_index' => true];
				$slug = $this->uniqueSlug($base, $usedSlugs);
				$this->writeBranch($node, $contentDir . '/' . $slug, $base);
			}
		}
	}

	/**
	 * Copy a page's attachments folder (`.attachments.{fileId}`) into the page's content directory,
	 * preserving the relative path so markdown image links keep resolving.
	 */
	private function copyAttachments(Folder $parent, int $fileId, string $destDir): void {
		$sourceName = '.attachments.' . $fileId;
		if (!$parent->nodeExists($sourceName)) {
			return;
		}
		$node = $parent->get($sourceName);
		if (!($node instanceof Folder)) {
			return;
		}
		// Hugo ignores dot-prefixed files, so publish attachments under a non-hidden name.
		// Markdown links are rewritten to match in rewriteAttachmentLinks().
		$this->copyNodeFolderToDisk($node, $destDir . '/attachments.' . $fileId);
	}

	/**
	 * Recursively copy a Nextcloud folder node to a local directory on disk.
	 */
	private function copyNodeFolderToDisk(Folder $folder, string $destDir): void {
		$this->ensureDir($destDir);
		try {
			$nodes = $folder->getDirectoryListing();
		} catch (FilesNotFoundException $e) {
			$this->logger->debug('Collective export: failed to list folder ' . $folder->getPath(), ['exception' => $e]);
			return;
		}

		foreach ($nodes as $node) {
			$target = $destDir . '/' . $node->getName();
			if ($node instanceof Folder) {
				$this->copyNodeFolderToDisk($node, $target);
				continue;
			}
			if ($node instanceof File) {
				try {
					file_put_contents($target, $this->nodeHelper->getContent($node));
				} catch (NotFoundException|NotPermittedException $e) {
					$this->logger->debug('Collective export: skipped file ' . $node->getPath(), ['exception' => $e]);
				}
			}
		}
	}

	private function readContent(File $file): string {
		try {
			return $this->rewriteAttachmentLinks($this->nodeHelper->getContent($file));
		} catch (NotFoundException|NotPermittedException $e) {
			$this->logger->debug('Collective export: skipped file ' . $file->getPath(), ['exception' => $e]);
			return '';
		}
	}

	/**
	 * Rewrite `.attachments.{id}/` references to the non-hidden `attachments.{id}/` folder
	 * used in the rendered site (see copyAttachments()).
	 */
	private function rewriteAttachmentLinks(string $body): string {
		return preg_replace('/\.attachments\.(\d+)\//', 'attachments.$1/', $body) ?? $body;
	}

	/**
	 * @throws NotPermittedException
	 */
	private function writeMarkdown(string $path, string $title, string $body): void {
		$frontmatter = "---\ntitle: " . $this->yamlString($title) . "\n---\n\n";
		if (file_put_contents($path, $frontmatter . $body) === false) {
			throw new NotPermittedException('Failed to write content file for collective export: ' . $path);
		}
	}

	private function shouldSkipNode(Node $node): bool {
		$name = $node->getName();
		if ($name === TemplateService::TEMPLATE_FOLDER) {
			return true;
		}

		// Attachments are copied per-page; other dot entries are skipped.
		return str_starts_with($name, '.');
	}

	/**
	 * @param array<string, true> $used
	 */
	private function uniqueSlug(string $name, array &$used): string {
		$slug = $this->slugify($name);
		$candidate = $slug;
		$i = 2;
		while (isset($used[$candidate])) {
			$candidate = $slug . '-' . $i;
			$i++;
		}
		$used[$candidate] = true;
		return $candidate;
	}

	private function slugify(string $name): string {
		$slug = mb_strtolower($name, 'UTF-8');
		$slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
		$slug = trim($slug, '-');
		return $slug !== '' ? $slug : 'page';
	}

	private function tomlString(string $value): string {
		return "'" . str_replace("'", '', $value) . "'";
	}

	private function yamlString(string $value): string {
		return '"' . addcslashes($value, '"\\') . '"';
	}

	/**
	 * @throws NotPermittedException
	 */
	private function ensureDir(string $dir): void {
		if (is_dir($dir)) {
			return;
		}
		if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
			throw new NotPermittedException('Failed to create directory for collective export: ' . $dir);
		}
	}

	/**
	 * @throws NotPermittedException
	 */
	private function copyDirectory(string $source, string $dest): void {
		$this->ensureDir($dest);
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
		);
		foreach ($iterator as $item) {
			$target = $dest . '/' . $iterator->getSubPathName();
			if ($item->isDir()) {
				$this->ensureDir($target);
			} else {
				if (copy($item->getPathname(), $target) === false) {
					throw new NotPermittedException('Failed to copy hugo skeleton file for collective export: ' . $target);
				}
			}
		}
	}

	/**
	 * @throws NotPermittedException
	 */
	private function zipDirectory(string $sourceDir, string $zipPath): void {
		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new NotPermittedException('Failed to create zip archive for collective export');
		}

		$sourceDir = rtrim($sourceDir, '/');
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST,
		);
		foreach ($iterator as $item) {
			$relativePath = $iterator->getSubPathName();
			if ($item->isDir()) {
				$zip->addEmptyDir($relativePath);
			} else {
				$zip->addFile($item->getPathname(), $relativePath);
			}
		}

		$zip->close();
	}
}
