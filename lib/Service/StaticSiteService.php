<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Db\StaticSiteMapper;

class StaticSiteService {
	public function __construct(
		private readonly StaticSiteMapper $staticSiteMapper,
		private readonly CollectiveService $collectiveService,
		private readonly PageService $pageService,
	) {
	}

	/**
	 * Create a selection of pages of a collective as a static site.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish, incl. the collective's root page
	 *
	 * @throws NotFoundException Collective or one of the pages not found
	 * @throws NotPermittedException User is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected
	 */
	public function create(int $collectiveId, array $pageIds, string $userId): StaticSite {
		$pageIds = array_map(intval(...), $pageIds);
		if (empty($pageIds)) {
			throw new UnprocessableEntityException('No pages selected for publishing');
		}

		$collective = $this->collectiveService->getCollective($collectiveId, $userId);
		if (!$collective->canEdit()) {
			throw new NotPermittedException('Not allowed to edit collective');
		}

		$this->verifyPagesBelongToCollective($collectiveId, $pageIds, $userId);

		return $this->staticSiteMapper->create($collectiveId, $pageIds, $userId);
	}

	/**
	 * @return StaticSite[]
	 *
	 * @throws NotFoundException Collective not found
	 * @throws NotPermittedException User is not allowed to access the collective
	 */
	public function getStaticSites(int $collectiveId, string $userId): array {
		$this->collectiveService->getCollective($collectiveId, $userId);

		return $this->staticSiteMapper->findByCollectiveId($collectiveId);
	}

	/**
	 * @param list<int> $pageIds
	 *
	 * @throws NotFoundException If a page doesn't belong to the collective
	 * @throws NotPermittedException
	 */
	private function verifyPagesBelongToCollective(int $collectiveId, array $pageIds, string $userId): void {
		$validPageIds = array_map(
			static fn ($pageInfo) => $pageInfo->getId(),
			$this->pageService->findAll($collectiveId, $userId)
		);

		$unknownPageIds = array_diff($pageIds, $validPageIds);
		if (!empty($unknownPageIds)) {
			throw new NotFoundException('Page(s) not found in collective: ' . implode(', ', $unknownPageIds));
		}
	}
}
