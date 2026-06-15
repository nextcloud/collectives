<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Fs\NodeHelper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\ITempManager;
use Psr\Log\LoggerInterface;
use ZipArchive;

class CollectiveExportService {
	public function __construct(
		private readonly CollectiveService $collectiveService,
		private readonly PageService $pageService,
		private readonly NodeHelper $nodeHelper,
		private readonly ITempManager $tempManager,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{0: string, 1: string} Path to zip file and suggested download filename
	 *
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function createZip(int $collectiveId, string $userId): array {
		$collective = $this->collectiveService->getCollective($collectiveId, $userId);
		$folder = $this->pageService->getCollectiveFolder($collectiveId, $userId);

		$zipPath = $this->tempManager->getTemporaryFile('zip');
		if ($zipPath === false) {
			throw new NotPermittedException('Failed to create temporary file for collective export');
		}

		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new NotPermittedException('Failed to create zip archive for collective export');
		}

		$this->addFolderToZip($zip, $folder, '');
		$zip->close();

		$filename = $this->nodeHelper->sanitiseFilename($collective->getName(), 'collective') . '.zip';

		return [$zipPath, $filename];
	}

	private function addFolderToZip(ZipArchive $zip, Folder $folder, string $prefix): void {
		try {
			$nodes = $folder->getDirectoryListing();
		} catch (FilesNotFoundException $e) {
			$this->logger->debug('Collective export: failed to list folder ' . $folder->getPath(), ['exception' => $e]);
			return;
		}

		foreach ($nodes as $node) {
			if ($this->shouldSkipNode($node)) {
				continue;
			}

			$entryName = $prefix . $node->getName();
			if ($node instanceof Folder) {
				$this->addFolderToZip($zip, $node, $entryName . '/');
				continue;
			}

			if (!($node instanceof File)) {
				continue;
			}

			try {
				$zip->addFromString($entryName, $this->nodeHelper->getContent($node));
			} catch (NotFoundException|NotPermittedException $e) {
				$this->logger->debug('Collective export: skipped file ' . $node->getPath(), ['exception' => $e]);
			}
		}
	}

	private function shouldSkipNode(Node $node): bool {
		$name = $node->getName();
		if (str_starts_with($name, '.') && !str_starts_with($name, '.attachments.')) {
			return true;
		}

		return false;
	}
}
