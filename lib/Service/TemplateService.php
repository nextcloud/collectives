<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;

class TemplateService {
	public const TEMPLATE_FOLDER = '.templates';
	public const TEMPLATE_INDEX_CONTENT = '## This folder contains template files for the collective';

	public function __construct(
		// private PageMapper $pageMapper,
		private PageService $pageService,
	) {}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getTemplateFolder(int $collectiveId, string $userId): Folder {
		$collectiveFolder = $this->pageService->getCollectiveFolder($collectiveId, $userId);

		try {
			$templateFolder = $collectiveFolder->get(self::TEMPLATE_FOLDER);
		} catch (FilesNotFoundException) {
			$templateFolder = $collectiveFolder->newFolder(self::TEMPLATE_FOLDER);
			$templateIndexInfo = $this->pageService->newPage($collectiveId, $templateFolder, PageInfo::INDEX_PAGE_TITLE, $userId);
			$templateIndexFile = $this->pageService->getPageFile($collectiveId, $templateIndexInfo->getId(), $userId);
			NodeHelper::putContent($templateIndexFile, self::TEMPLATE_INDEX_CONTENT);
		}
		if (!($templateFolder instanceof Folder)) {
			throw new NotFoundException('Failed to get template folder');
		}

		return $templateFolder;
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getTemplates(int $collectiveId, string $userId): array {
		$templateFolder = $this->getTemplateFolder($collectiveId, $userId);
		$templatePages = $this->pageService->getPagesFromFolder($collectiveId, $templateFolder, $userId, true);
		// Filter out index page
		return array_values(array_filter($templatePages, static fn (PageInfo $page) => ($page->getTitle() !== self::TEMPLATE_FOLDER)));
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function create(int $collectiveId, ?int $parentId, string $title, string $userId): PageInfo {
		$parentId = $parentId !== 0
			? $parentId
			: $this->pageService->getIndexPageFile($this->getTemplateFolder($collectiveId, $userId))->getId();
		return $this->pageService->create($collectiveId, $parentId, $title, $userId, true);
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function rename(int $collectiveId, int $id, string $title, string $userId): PageInfo {
		$parentId = $this->pageService->getIndexPageFile($this->getTemplateFolder($collectiveId, $userId))->getId();
		return $this->pageService->move($collectiveId, $id, $parentId, $title, 0, $userId);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setEmoji(int $collectiveId, int $id, ?string $emoji, string $userId): PageInfo {
		return $this->pageService->setEmoji($collectiveId, $id, $emoji, $userId);
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function delete(int $collectiveId, int $id, string $userId): void {
		$templateFolder = $this->getTemplateFolder($collectiveId, $userId);
		$this->pageService->trash($collectiveId, $id, $userId, true, $templateFolder);
	}
}
