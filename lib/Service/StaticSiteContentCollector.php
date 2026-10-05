<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;

/**
 * Collects the files of a collective that are part of its static site.
 */
class StaticSiteContentCollector {
	public function __construct(
		private readonly CollectiveFolderManager $collectiveFolderManager,
	) {
	}

	/**
	 * Collect all pages, attachments, and other files of the collective, except templates.
	 *
	 * Files are accessed without user context, permissions have to be checked by the caller.
	 *
	 * @return array<string, File> Files mapped to their path relative to the collective folder
	 *
	 * @throws FilesNotFoundException
	 * @throws FilesNotPermittedException
	 * @throws InvalidPathException
	 */
	public function collect(int $collectiveId): array {
		return $this->collectFiles($this->collectiveFolderManager->getFolder($collectiveId));
	}

	/**
	 * @return array<string, File>
	 *
	 * @throws FilesNotFoundException
	 * @throws FilesNotPermittedException
	 */
	private function collectFiles(Folder $folder, string $prefix = ''): array {
		$files = [];
		foreach ($folder->getDirectoryListing() as $node) {
			$path = $prefix . $node->getName();
			if ($path === TemplateService::TEMPLATE_FOLDER) {
				continue;
			}

			if ($node instanceof Folder) {
				$files += $this->collectFiles($node, $path . '/');
			} elseif ($node instanceof File) {
				$files[$path] = $node;
			}
		}
		return $files;
	}
}
