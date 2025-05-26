<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IAppConfig;
use OCP\Lock\LockedException;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class MigrateTemplates implements IRepairStep {
	private const TEMPLATE_PAGE_TITLE = 'Template';
	private const TEMPLATE_FOLDER = '.templates';
	private const TEMPLATE_INDEX_CONTENT = '## This folder contains template files for the collective';

	public function __construct(
		private IAppConfig $config,
		private CollectiveFolderManager $collectiveFolderManager,
		private CollectiveMapper $collectiveMapper,
	) {
	}

	public function getName():string {
		return 'Migrate templates to new implementation logic';
	}

	/**
	 * @throws NotPermittedException
	 */
	public static function putContent(File $file, string $content): void {
		if (!mb_check_encoding($content, 'UTF-8')) {
			$content = mb_convert_encoding($content, 'UTF-8');
		}

		try {
			$file->putContent($content);
		} catch (FilesNotPermittedException|LockedException $e) {
			throw new NotPermittedException('Failed to write file content for ' . $file->getPath(), 0, $e);
		}
	}

	/**
	 * @return File[]
	 */
	private function getFolderTemplateFiles(Folder $folder, bool $recursive = false): array {
		try {
			$lsNodes = $folder->getDirectoryListing();
		} catch (FilesNotFoundException) {
			return [];
		}

		$templateFiles = [];
		$templateFilesRecursive = [];
		foreach ($lsNodes as $node) {
			if ($recursive && $node instanceof Folder) {
				$templateFilesRecursive[] = $this->getFolderTemplateFiles($node, true);
			}

			if ($node instanceof File === false || $node->getName() !== self::TEMPLATE_PAGE_TITLE . PageInfo::SUFFIX) {
				continue;
			}

			$templateFiles[] = $node;
		}

		return array_merge($templateFiles, ...$templateFilesRecursive);
	}

	/**
	 * @throws FilesNotPermittedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getTemplateFolder(Folder $collectiveFolder): Folder {
		try {
			/** @var Folder $templateFolder */
			$templateFolder = $collectiveFolder->get(self::TEMPLATE_FOLDER);
		} catch (FilesNotFoundException) {
			// Create missing template folder
			$templateFolder = $collectiveFolder->newFolder(self::TEMPLATE_FOLDER);
			$templateIndexFile = $templateFolder->newFile(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
			self::putContent($templateIndexFile, self::TEMPLATE_INDEX_CONTENT);
		}

		return $templateFolder;
	}

	public function run(IOutput $output): void {
		$appVersion = $this->config->getValueString('collectives', 'installed_version');

		if (!$appVersion || version_compare($appVersion, '2.17.0') !== -1) {
			return;
		}

		$output->startProgress();
		foreach ($this->collectiveMapper->getAll() as $collective) {
			try {
				$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
			} catch (InvalidPathException|FilesNotFoundException) {
				$output->warning('Failed to migrate templates for collective with id ' . $collective->getId());
				continue;
			}

			$templateFiles = $this->getFolderTemplateFiles($collectiveFolder, true);
			if ($templateFiles === []) {
				continue;
			}

			try {
				$templateFolder = $this->getTemplateFolder($collectiveFolder);
			} catch (NotFoundException|NotPermittedException|FilesNotPermittedException) {
				$output->warning('Failed to migrate templates for collective with id ' . $collective->getId());
				continue;
			}

			foreach ($templateFiles as $templateFile) {
				$parentName = $templateFile->getParent()->getName();

				$filename = NodeHelper::generateFilename($templateFolder, $parentName, PageInfo::SUFFIX);
				$templateFile->move($templateFolder->getPath() . '/' . $filename . PageInfo::SUFFIX);
				$output->advance();
			}
		}
		$output->finishProgress();
	}
}
