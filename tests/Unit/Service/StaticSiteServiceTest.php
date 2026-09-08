<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Db\StaticSiteMapper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\StaticSiteService;
use OCA\Collectives\Service\UnprocessableEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class StaticSiteServiceTest extends TestCase {
	private StaticSiteMapper&MockObject $staticSiteMapper;
	private CollectiveService&MockObject $collectiveService;
	private PageService&MockObject $pageService;
	private StaticSiteService $service;

	private string $userId = 'alice';
	private int $collectiveId = 42;

	protected function setUp(): void {
		parent::setUp();

		$this->staticSiteMapper = $this->createMock(StaticSiteMapper::class);
		$this->collectiveService = $this->createMock(CollectiveService::class);
		$this->pageService = $this->createMock(PageService::class);

		$this->service = new StaticSiteService(
			$this->staticSiteMapper,
			$this->collectiveService,
			$this->pageService,
		);
	}

	private function makeCollective(bool $canEdit): Collective {
		$collective = $this->createMock(Collective::class);
		$collective->method('canEdit')->willReturn($canEdit);
		return $collective;
	}

	private function makePageInfo(int $id): PageInfo {
		$page = $this->createMock(PageInfo::class);
		$page->method('getId')->willReturn($id);
		return $page;
	}

	// --- publish() ---

	public function testPublishCreatesStaticSiteForValidPages(): void {
		$pageIds = [1, 2, 3];
		$collective = $this->makeCollective(canEdit: true);

		$this->collectiveService->method('getCollective')
			->with($this->collectiveId, $this->userId)
			->willReturn($collective);

		$this->pageService->method('findAll')
			->with($this->collectiveId, $this->userId)
			->willReturn(array_map($this->makePageInfo(...), $pageIds));

		$staticSite = new StaticSite();
		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, $pageIds, $this->userId)
			->willReturn($staticSite);

		$result = $this->service->create($this->collectiveId, $pageIds, $this->userId);

		$this->assertSame($staticSite, $result);
	}

	public function testPublishCastsPageIdsToInt(): void {
		// Simulates JSON body parsed by OCS framework where IDs may arrive as strings
		$pageIds = ['1', '2'];
		$collective = $this->makeCollective(canEdit: true);

		$this->collectiveService->method('getCollective')->willReturn($collective);
		$this->pageService->method('findAll')->willReturn([
			$this->makePageInfo(1),
			$this->makePageInfo(2),
		]);

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [1, 2], $this->userId);

		$this->service->create($this->collectiveId, $pageIds, $this->userId);
	}

	public function testPublishThrowsWhenNoPageIdsGiven(): void {
		$this->expectException(UnprocessableEntityException::class);

		$this->staticSiteMapper->expects($this->never())->method('create');

		$this->service->create($this->collectiveId, [], $this->userId);
	}

	public function testPublishThrowsWhenUserCannotEdit(): void {
		$collective = $this->makeCollective(canEdit: false);
		$this->collectiveService->method('getCollective')->willReturn($collective);

		$this->expectException(NotPermittedException::class);
		$this->staticSiteMapper->expects($this->never())->method('create');

		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishThrowsWhenCollectiveNotAccessible(): void {
		$this->collectiveService->method('getCollective')
			->willThrowException(new NotFoundException('Collective not found'));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('create');

		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishRejectsPageIdsFromOtherCollective(): void {
		// IDOR guard: pageId 99 belongs to a different collective
		$collective = $this->makeCollective(canEdit: true);
		$this->collectiveService->method('getCollective')->willReturn($collective);
		$this->pageService->method('findAll')->willReturn([
			$this->makePageInfo(1),
			$this->makePageInfo(2),
		]);

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('create');

		$this->service->create($this->collectiveId, [1, 99], $this->userId);
	}

	// --- getStaticSites() ---

	public function testGetStaticSitesReturnsListForAccessibleCollective(): void {
		$collective = $this->makeCollective(canEdit: true);
		$this->collectiveService->method('getCollective')
			->with($this->collectiveId, $this->userId)
			->willReturn($collective);

		$sites = [new StaticSite(), new StaticSite()];
		$this->staticSiteMapper->method('findByCollectiveId')
			->with($this->collectiveId)
			->willReturn($sites);

		$result = $this->service->getStaticSites($this->collectiveId, $this->userId);

		$this->assertSame($sites, $result);
	}

	public function testGetStaticSitesThrowsWhenCollectiveNotAccessible(): void {
		$this->collectiveService->method('getCollective')
			->willThrowException(new NotFoundException('Collective not found'));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('findByCollectiveId');

		$this->service->getStaticSites($this->collectiveId, $this->userId);
	}
}
