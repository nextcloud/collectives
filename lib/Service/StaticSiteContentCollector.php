<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;

/**
 * Collects the files of the selected pages of a collective that are part of its static site.
 */
class StaticSiteContentCollector {
	private const ATTACHMENT_FOLDER_PREFIX = '.attachments.';

	public function __construct(
		private readonly CollectiveFolderManager $collectiveFolderManager,
	) {
	}

	/**
	 * Collect the selected pages of the collective with their attachments, except templates.
	 *
	 * Unselected pages are left out, including their subpages and attachments. The selection
	 * has to contain the ancestors of every selected page, see `StaticSiteService`.
	 *
	 * Files are accessed without user context, permissions have to be checked by the caller.
	 *
	 * @param list<int> $pageIds IDs of the pages to collect
	 *
	 * @return array<string, File> Files mapped to their path relative to the collective folder
	 *
	 * @throws FilesNotFoundException
	 * @throws FilesNotPermittedException
	 * @throws InvalidPathException
	 */
	public function collect(int $collectiveId, array $pageIds): array {
		$collectiveFolder = $this->collectiveFolderManager->getFolder($collectiveId);

		return $this->collectFiles($collectiveFolder, array_fill_keys($pageIds, true));
	}

	/**
	 * A page is stored as a folder with an index page if it has subpages, as a single file otherwise.
	 *
	 * @param array<int, true> $selectedPageIds Selected page IDs as a lookup set
	 *
	 * @return array<string, File>
	 *
	 * @throws FilesNotFoundException
	 * @throws FilesNotPermittedException
	 */
	private function collectFiles(Folder $folder, array $selectedPageIds, string $prefix = ''): array {
		$nodes = $folder->getDirectoryListing();

		$indexPage = self::findIndexPage($nodes);
		if ($indexPage === null) {
			// Not a page folder, e.g. one uploaded via the Files app: keep its content as is
			return $this->collectAllFiles($nodes, $prefix);
		}
		if (!isset($selectedPageIds[$indexPage->getId()])) {
			// Unselected page, skip it with all its subpages and attachments
			return [];
		}

		$files = [];
		foreach ($nodes as $node) {
			$path = $prefix . $node->getName();
			if ($path === TemplateService::TEMPLATE_FOLDER) {
				continue;
			}

			if ($node instanceof File) {
				// Everything but a subpage is an attachment of this page
				if (!NodeHelper::isPage($node) || isset($selectedPageIds[$node->getId()])) {
					$files[$path] = $node;
				}
			} elseif ($node instanceof Folder) {
				$files += $this->collectSubFolder($node, $selectedPageIds, $path);
			}
		}

		return $files;
	}

	/**
	 * A subfolder either holds the attachments of a page of the parent folder or a subpage.
	 *
	 * @param array<int, true> $selectedPageIds
	 *
	 * @return array<string, File>
	 *
	 * @throws FilesNotFoundException
	 * @throws FilesNotPermittedException
	 */
	private function collectSubFolder(Folder $folder, array $selectedPageIds, string $path): array {
		$name = $folder->getName();
		if (!str_starts_with($name, self::ATTACHMENT_FOLDER_PREFIX)) {
			return $this->collectFiles($folder, $selectedPageIds, $path . '/');
		}

		// Attachment folders are named after the ID of the page they belong to
		$attachedToPageId = (int)substr($name, strlen(self::ATTACHMENT_FOLDER_PREFIX));
		return isset($selectedPageIds[$attachedToPageId])
			? $this->collectAllFiles($folder->getDirectoryListing(), $path . '/')
			: [];
	}

	/**
	 * @param Node[] $nodes
	 *
	 * @return array<string, File>
	 *
	 * @throws FilesNotFoundException
	 */
	private function collectAllFiles(array $nodes, string $prefix): array {
		$files = [];
		foreach ($nodes as $node) {
			$path = $prefix . $node->getName();
			if ($node instanceof Folder) {
				$files += $this->collectAllFiles($node->getDirectoryListing(), $path . '/');
			} elseif ($node instanceof File) {
				$files[$path] = $node;
			}
		}
		return $files;
	}

	/**
	 * @param Node[] $nodes
	 */
	private static function findIndexPage(array $nodes): ?File {
		foreach ($nodes as $node) {
			if ($node instanceof File && NodeHelper::isIndexPage($node)) {
				return $node;
			}
		}
		return null;
	}
}
