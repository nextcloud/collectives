<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Model\PageInfo;
use OCP\Files\NotFoundException as FilesNotFoundException;

class TemplateService {

	public function __construct(
		private PageService $pageService,
	) {}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getTemplates(int $collectiveId, string $userId): array {
		$templateFolder = $this->pageService->getTemplateFolder($collectiveId, $userId);
		$templatePages = $this->pageService->getPagesFromFolder($collectiveId, $templateFolder, $userId, true);
		// Filter out index page
		return array_values(array_filter(
			$templatePages,
			static fn (PageInfo $page) => (!($page->getFilePath() === PageService::TEMPLATE_FOLDER && $page->getFileName() === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX))
		));
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
			: $this->pageService->getIndexPageFile($this->pageService->getTemplateFolder($collectiveId, $userId))->getId();
		return $this->pageService->create($collectiveId, $parentId, $title, null, $userId, true);
	}

	/**
	 * @throws FilesNotFoundException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function rename(int $collectiveId, int $id, string $title, string $userId): PageInfo {
		$parentId = $this->pageService->getIndexPageFile($this->pageService->getTemplateFolder($collectiveId, $userId))->getId();
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
		$templateFolder = $this->pageService->getTemplateFolder($collectiveId, $userId);
		$this->pageService->trash($collectiveId, $id, $userId, true, $templateFolder);
	}
}
