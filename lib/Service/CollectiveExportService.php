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
	public function createZip(int $collectiveId, int $pageId, string $userId): array {
		$collective = $this->collectiveService->getCollective($collectiveId, $userId);
		$pageInfo = $this->pageService->find($collectiveId, $pageId, $userId);
		$pageFile = $this->pageService->getPageFile($collectiveId, $pageId, $userId);
		$collectiveFolder = $this->pageService->getCollectiveFolder($collectiveId, $userId);

		$zipPath = $this->tempManager->getTemporaryFile('zip');
		if ($zipPath === false) {
			throw new NotPermittedException('Failed to create temporary file for collective export');
		}

		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new NotPermittedException('Failed to create zip archive for collective export');
		}

		if (NodeHelper::isLandingPage($pageFile)) {
			$this->addFolderToZip($zip, $collectiveFolder, '');
		} elseif (NodeHelper::isIndexPage($pageFile)) {
			$folder = $pageFile->getParent();
			if (!($folder instanceof Folder)) {
				throw new NotFoundException('Failed to get folder for page ' . $pageId);
			}
			$this->addFolderToZip($zip, $folder, $folder->getName() . '/');
		} else {
			$this->addPageFileToZip($zip, $pageFile, '');
		}

		$zip->close();

		$title = $pageInfo->getTitle() !== ''
			? $pageInfo->getTitle()
			: $collective->getName();
		$filename = $this->nodeHelper->sanitiseFilename($title, 'page') . '.zip';

		return [$zipPath, $filename];
	}

	private function addPageFileToZip(ZipArchive $zip, File $pageFile, string $prefix): void {
		$entryName = $prefix . $pageFile->getName();
		try {
			$zip->addFromString($entryName, $this->nodeHelper->getContent($pageFile));
		} catch (NotFoundException|NotPermittedException $e) {
			$this->logger->debug('Collective export: skipped file ' . $pageFile->getPath(), ['exception' => $e]);
		}

		$parent = $pageFile->getParent();
		if (!($parent instanceof Folder)) {
			return;
		}

		$attachmentsFolder = '.attachments.' . $pageFile->getId();
		if ($parent->nodeExists($attachmentsFolder)) {
			$node = $parent->get($attachmentsFolder);
			if ($node instanceof Folder) {
				$this->addFolderToZip($zip, $node, $prefix . $attachmentsFolder . '/');
			}
		}

		$baseName = basename($pageFile->getName(), PageInfo::SUFFIX);
		if ($parent->nodeExists($baseName)) {
			$node = $parent->get($baseName);
			if ($node instanceof Folder) {
				$this->addFolderToZip($zip, $node, $prefix . $baseName . '/');
			}
		}
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
		if ($name === TemplateService::TEMPLATE_FOLDER) {
			return true;
		}

		if (str_starts_with($name, '.') && !str_starts_with($name, '.attachments.')) {
			return true;
		}

		return false;
	}
}
