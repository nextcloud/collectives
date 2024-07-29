<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Model\PageInfo;

class SharePageService {
	public function __construct(
		private PageService $pageService,
		private CollectiveShareService $shareService,
	) {
	}

	/**
	 * @throws NotFoundException
	 */
	public function findSharePageById(string $shareToken, int $pageId): PageInfo {
		// Don't decorate share with editing permissions (for performance reasons)
		if (null === $collectiveShare = $this->shareService->findShareByToken($shareToken, false)) {
			throw new NotFoundException('Page or share not found');
		}
		$parentId = $collectiveShare->getPageId() === 0 ? null : $collectiveShare->getPageId();
		return $this->pageService->findByFileId($collectiveShare->getCollectiveId(), $pageId, $collectiveShare->getOwner(), $parentId);
	}

	/**
	 * @throws NotFoundException
	 */
	public function findSharePageByPath(string $shareToken, string $path): PageInfo {
		// Don't decorate share with editing permissions (for performance reasons)
		if (null === $collectiveShare = $this->shareService->findShareByToken($shareToken, false)) {
			throw new NotFoundException('Page or share not found');
		}
		$parentId = $collectiveShare->getPageId() === 0 ? null : $collectiveShare->getPageId();
		return $this->pageService->findByPath($collectiveShare->getCollectiveId(), $path, $collectiveShare->getOwner(), $parentId);
	}
}
