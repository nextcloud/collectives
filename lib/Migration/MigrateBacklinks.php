<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use League\CommonMark\Exception\CommonMarkException;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\PageLinkMapper;
use OCA\Collectives\Fs\MarkdownHelper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class MigrateBacklinks implements IRepairStep {
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
		private readonly CollectiveFolderManager $collectiveFolderManager,
		private readonly CollectiveMapper $collectiveMapper,
		private readonly PageLinkMapper $pageLinkMapper,
	) {
	}

	public function getName():string {
		return 'Cache existing backlinks in database';
	}

	private function pagesFromFolder(Folder $folder): array {
		try {
			$lsNodes = $folder->getDirectoryListing();
		} catch (FilesNotFoundException) {
			return [];
		}

		$pageFiles = [];
		$pageFilesRecursive = [];
		foreach ($lsNodes as $node) {
			if ($node instanceof Folder) {
				$pageFilesRecursive = $this->pagesFromFolder($node);
				continue;
			}

			if ($node instanceof File && $node->getMimeType() === 'text/markdown') {
				$pageFiles[] = $node;
			}
		}

		return array_merge($pageFiles, $pageFilesRecursive);
	}

	public function run(IOutput $output): void {
		if ($this->appConfig->getValueBool('collectives', 'migrated_backlinks')) {
			$output->info('Backlinks already cached');
			return;
		}

		$output->info('Caching backlinks for pages ...');
		$output->startProgress();
		$trustedDomains = $this->config->getSystemValue('trusted_domains', []);
		foreach ($this->collectiveMapper->getAll() as $collective) {
			try {
				$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
			} catch (InvalidPathException|FilesNotFoundException) {
				$output->warning('Failed to migrate templates for collective with id ' . $collective->getId());
				continue;
			}

			$pageFiles = $this->pagesFromFolder($collectiveFolder);

			foreach ($pageFiles as $pageFile) {
				try {
					$linkedPageIds = MarkdownHelper::getLinkedPageIds($collective, $pageFile->getContent(), $trustedDomains);
					$this->pageLinkMapper->updateByPageId($pageFile->getId(), $linkedPageIds);
				} catch (CommonMarkException) {
				}
				$output->advance();
			}
		}

		$output->finishProgress();
		$output->info('done');

		$this->appConfig->setValueBool('collectives', 'migrated_backlinks', true);
	}
}
