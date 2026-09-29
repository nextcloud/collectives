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
use OCA\Collectives\Service\SlugService;
use OCA\Collectives\Service\StaticSiteService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\DB\Exception as DBException;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\String\Slugger\AsciiSlugger;
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
			new SlugService(new AsciiSlugger()),
		);
	}

	private function makeCollective(bool $canEdit, string $name = 'My Collective'): Collective {
		$collective = $this->createMock(Collective::class);
		$collective->method('canEdit')->willReturn($canEdit);
		$collective->method('getName')->willReturn($name);
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
			->with($this->collectiveId, $pageIds, 'My Collective', 'my-collective', $this->userId)
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
			->with($this->collectiveId, [1, 2], $this->anything(), $this->anything(), $this->userId);

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

	private function expectValidCollectiveWithPages(string $name = 'My Collective'): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true, name: $name));
		$this->pageService->method('findAll')->willReturn([$this->makePageInfo(1)]);
	}

	public function testPublishUsesGivenTitleAndSlug(): void {
		$this->expectValidCollectiveWithPages();

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [1], 'Our Site', 'our-site-2026', $this->userId);

		$this->service->create($this->collectiveId, [1], $this->userId, '  Our Site ', 'our-site-2026');
	}

	public function testPublishDefaultSlugIsTruncated(): void {
		$name = str_repeat('a', 70);
		$this->expectValidCollectiveWithPages($name);

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [1], $name, str_repeat('a', 64), $this->userId);

		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public static function invalidTitleProvider(): array {
		return [
			'empty' => [''],
			'whitespace only' => ['   '],
			'too long' => [str_repeat('a', 256)],
		];
	}

	/**
	 * @dataProvider invalidTitleProvider
	 */
	public function testPublishRejectsInvalidTitle(string $title): void {
		$this->expectValidCollectiveWithPages();

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('create');

		$this->service->create($this->collectiveId, [1], $this->userId, $title);
	}

	public static function invalidSlugProvider(): array {
		return [
			'empty' => [''],
			'uppercase' => ['My-Site'],
			'space' => ['my site'],
			'underscore' => ['my_site'],
			'non-ascii' => ['über'],
			'leading hyphen' => ['-abc'],
			'trailing hyphen' => ['abc-'],
			'double hyphen' => ['a--b'],
			'hyphen only' => ['-'],
			'too long' => [str_repeat('a', 65)],
		];
	}

	/**
	 * @dataProvider invalidSlugProvider
	 */
	public function testPublishRejectsInvalidSlug(string $slug): void {
		$this->expectValidCollectiveWithPages();

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('create');

		$this->service->create($this->collectiveId, [1], $this->userId, null, $slug);
	}

	public function testPublishThrowsWhenSlugAlreadyInUse(): void {
		$this->expectValidCollectiveWithPages();

		$dbException = $this->createMock(DBException::class);
		$dbException->method('getReason')->willReturn(DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
		$this->staticSiteMapper->method('create')->willThrowException($dbException);

		$this->expectException(UnprocessableEntityException::class);

		$this->service->create($this->collectiveId, [1], $this->userId, null, 'taken');
	}

	public function testPublishRethrowsOtherDatabaseErrors(): void {
		$this->expectValidCollectiveWithPages();

		$dbException = $this->createMock(DBException::class);
		$dbException->method('getReason')->willReturn(DBException::REASON_DRIVER);
		$this->staticSiteMapper->method('create')->willThrowException($dbException);

		$this->expectException(DBException::class);

		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	// --- update() ---

	private function makeStaticSite(string $status): StaticSite {
		$staticSite = new StaticSite();
		$staticSite->setId(7);
		$staticSite->setCollectiveId($this->collectiveId);
		$staticSite->setSlug('my-collective');
		$staticSite->setStatus($status);
		return $staticSite;
	}

	public static function finishedStatusProvider(): array {
		return [
			'published' => [StaticSite::STATUS_PUBLISHED],
			'failed' => [StaticSite::STATUS_FAILED],
		];
	}

	/**
	 * @dataProvider finishedStatusProvider
	 */
	public function testUpdateRepublishesFinishedStaticSite(string $status): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite($status);

		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->with(7, $this->collectiveId)
			->willReturn($staticSite);
		$this->staticSiteMapper->expects($this->once())
			->method('republish')
			->with($staticSite, 'New Title', [1])
			->willReturn($staticSite);

		$result = $this->service->update($this->collectiveId, 7, ['1'], ' New Title ', $this->userId);

		$this->assertSame($staticSite, $result);
	}

	public static function inProgressStatusProvider(): array {
		return [
			'pending' => [StaticSite::STATUS_PENDING],
			'provided' => [StaticSite::STATUS_PROVIDED],
			'fetched' => [StaticSite::STATUS_FETCHED],
		];
	}

	/**
	 * @dataProvider inProgressStatusProvider
	 */
	public function testUpdateRejectsStaticSiteInProgress(string $status): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite($status));

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('republish');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenNoPageIdsGiven(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('republish');

		$this->service->update($this->collectiveId, 7, [], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenUserCannotEdit(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: false));

		$this->expectException(NotPermittedException::class);
		$this->staticSiteMapper->expects($this->never())->method('findByIdAndCollectiveId');
		$this->staticSiteMapper->expects($this->never())->method('republish');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenStaticSiteNotInCollective(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->willThrowException(new NotFoundException('Static site not found'));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('republish');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	/**
	 * @dataProvider invalidTitleProvider
	 */
	public function testUpdateRejectsInvalidTitle(string $title): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('republish');

		$this->service->update($this->collectiveId, 7, [1], $title, $this->userId);
	}

	public function testUpdateRejectsPageIdsFromOtherCollective(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('republish');

		$this->service->update($this->collectiveId, 7, [1, 99], 'Title', $this->userId);
	}

	// --- delete() ---

	/**
	 * @dataProvider finishedStatusProvider
	 * @dataProvider inProgressStatusProvider
	 */
	public function testDeleteRemovesStaticSite(string $status): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$staticSite = $this->makeStaticSite($status);

		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->with(7, $this->collectiveId)
			->willReturn($staticSite);
		$this->staticSiteMapper->expects($this->once())
			->method('delete')
			->with($staticSite);

		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	public function testDeleteThrowsWhenUserCannotEdit(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: false));

		$this->expectException(NotPermittedException::class);
		$this->staticSiteMapper->expects($this->never())->method('findByIdAndCollectiveId');
		$this->staticSiteMapper->expects($this->never())->method('delete');

		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	public function testDeleteThrowsWhenStaticSiteNotInCollective(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->willThrowException(new NotFoundException('Static site not found'));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('delete');

		$this->service->delete($this->collectiveId, 7, $this->userId);
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
