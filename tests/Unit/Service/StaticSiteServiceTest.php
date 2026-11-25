<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\ConfigLexicon;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Db\StaticSiteMapper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\ServiceException;
use OCA\Collectives\Service\SlugService;
use OCA\Collectives\Service\StaticSiteArchiver;
use OCA\Collectives\Service\StaticSiteBuildNotFoundException;
use OCA\Collectives\Service\StaticSiteContentCollector;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteGeneratorNotConfiguredException;
use OCA\Collectives\Service\StaticSiteGeneratorUnavailableException;
use OCA\Collectives\Service\StaticSitePublicationInProgressException;
use OCA\Collectives\Service\StaticSiteRemover;
use OCA\Collectives\Service\StaticSiteService;
use OCA\Collectives\Service\StaticSiteSlugTakenException;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DBException;
use OCP\Files\File;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;
use Test\TestCase;

class StaticSiteServiceTest extends TestCase {
	private StaticSiteMapper&MockObject $staticSiteMapper;
	private CollectiveService&MockObject $collectiveService;
	private PageService&MockObject $pageService;
	private StaticSiteContentCollector&MockObject $contentCollector;
	private StaticSiteArchiver&MockObject $archiver;
	private StaticSiteGeneratorClient&MockObject $generatorClient;
	private StaticSiteRemover&MockObject $remover;
	private LoggerInterface&MockObject $logger;
	private StaticSiteService $service;

	private string $userId = 'alice';
	private int $collectiveId = 42;
	private string $staticSiteId = '01990000-0000-7000-8000-000000000000';
	private string $buildId = '01990000-0000-7000-8000-000000000001';
	private int $now = 1800000000;
	private int $maxSize = ConfigLexicon::STATIC_SITE_MAX_SIZE_DEFAULT;
	private bool $publishFeatureEnabled = true;

	protected function setUp(): void {
		parent::setUp();

		$this->staticSiteMapper = $this->createMock(StaticSiteMapper::class);
		$this->collectiveService = $this->createMock(CollectiveService::class);
		$this->pageService = $this->createMock(PageService::class);
		$this->contentCollector = $this->createMock(StaticSiteContentCollector::class);
		$this->archiver = $this->createMock(StaticSiteArchiver::class);
		$this->generatorClient = $this->createMock(StaticSiteGeneratorClient::class);
		$this->remover = $this->createMock(StaticSiteRemover::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn($this->now);
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueInt')
			->with('collectives', ConfigLexicon::STATIC_SITE_MAX_SIZE, ConfigLexicon::STATIC_SITE_MAX_SIZE_DEFAULT)
			->willReturnCallback(fn (): int => $this->maxSize);
		$appConfig->method('getValueBool')
			->with('collectives', ConfigLexicon::PUBLISH_ENABLED, ConfigLexicon::PUBLISH_ENABLED_DEFAULT)
			->willReturnCallback(fn (): bool => $this->publishFeatureEnabled);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => vsprintf($text, $parameters));

		$this->service = new StaticSiteService(
			$this->staticSiteMapper,
			$this->collectiveService,
			$this->pageService,
			new SlugService(new AsciiSlugger()),
			$this->contentCollector,
			$this->archiver,
			$this->generatorClient,
			$this->remover,
			$this->logger,
			$timeFactory,
			$appConfig,
			$l10n,
		);
	}

	private function makeCollective(bool $canEdit, string $name = 'My Collective'): Collective {
		$collective = $this->createMock(Collective::class);
		$collective->method('canEdit')->willReturn($canEdit);
		$collective->method('getName')->willReturn($name);
		return $collective;
	}

	private function makeStaticSite(string $status, int $updatedAgo = 60): StaticSite {
		$staticSite = new StaticSite();
		$staticSite->setUpdatedAt($this->now - $updatedAgo);
		$staticSite->setId(7);
		$staticSite->setCollectiveId($this->collectiveId);
		$staticSite->setStaticSiteId($this->staticSiteId);
		$staticSite->setBuildId($this->buildId);
		$staticSite->setSlug('my-collective');
		$staticSite->setStatus($status);
		return $staticSite;
	}

	private function isNewBuildId(): Callback {
		return $this->callback(fn (string $buildId): bool => Uuid::isValid($buildId) && $buildId !== $this->buildId);
	}

	private function makePageInfo(int $id, int $parentId = 0): PageInfo {
		$page = $this->createMock(PageInfo::class);
		$page->method('getId')->willReturn($id);
		$page->method('getParentId')->willReturn($parentId);
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

		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PENDING);
		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, $pageIds, 'My Collective', 'my-collective', $this->userId, StaticSite::THEME_WIKI)
			->willReturn($staticSite);

		$files = ['Readme.md' => $this->makeFileWithSize(1)];
		$this->contentCollector->expects($this->once())
			->method('collect')
			->with($this->collectiveId, $pageIds)
			->willReturn($files);
		$this->archiver->expects($this->once())
			->method('store')
			->with($this->staticSiteId, $this->buildId, $files);
		$this->archiver->expects($this->once())
			->method('deleteAllExcept')
			->with($this->staticSiteId, $this->buildId);
		$this->generatorClient->expects($this->once())
			->method('create')
			->with($staticSite, 'My Collective', StaticSite::THEME_WIKI);

		$provided = $this->makeStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->once())
			->method('updateRequestStatus')
			->with($staticSite, StaticSite::STATUS_PROVIDED)
			->willReturn(true);
		$this->staticSiteMapper->method('find')->with(7)->willReturn($provided);

		$result = $this->service->create($this->collectiveId, $pageIds, $this->userId);

		$this->assertSame($provided, $result);
	}

	public function testPublishSucceedsIfArchivesOfOtherBuildsCannotBeDeleted(): void {
		$this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn(['Readme.md' => $this->makeFileWithSize(1)]);
		$this->archiver->method('deleteAllExcept')->willThrowException(new ServiceException('Failed'));
		$this->logger->expects($this->once())
			->method('warning')
			->with('Failed to delete archives of previous static site builds');
		$this->generatorClient->expects($this->once())->method('create');
		$this->staticSiteMapper->expects($this->once())
			->method('updateRequestStatus')
			->with($this->anything(), StaticSite::STATUS_PROVIDED);

		$this->service->create($this->collectiveId, [1], $this->userId);
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
			->with($this->collectiveId, [1, 2], $this->anything(), $this->anything(), $this->userId, $this->anything())
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));

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

	public function testPublishAddsMissingAncestorPagesToKeepHierarchyIntact(): void {
		$collective = $this->makeCollective(canEdit: true);
		$this->collectiveService->method('getCollective')->willReturn($collective);
		$this->pageService->method('findAll')->willReturn([
			$this->makePageInfo(1),
			$this->makePageInfo(2, parentId: 1),
			$this->makePageInfo(3, parentId: 2),
		]);

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [3, 2, 1], 'My Collective', 'my-collective', $this->userId, StaticSite::THEME_WIKI)
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));

		// Only the deepest subpage was explicitly selected
		$this->service->create($this->collectiveId, [3], $this->userId);
	}

	public function testPublishResolvesAncestorsOfAnUnorderedSelectionWithoutDuplicates(): void {
		$collective = $this->makeCollective(canEdit: true);
		$this->collectiveService->method('getCollective')->willReturn($collective);
		$this->pageService->method('findAll')->willReturn([
			$this->makePageInfo(1),
			$this->makePageInfo(2, parentId: 1),
			$this->makePageInfo(3, parentId: 2),
		]);

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [3, 2, 1], 'My Collective', 'my-collective', $this->userId, StaticSite::THEME_WIKI)
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));

		// The root page is selected after its grandchild, which already pulled it in
		$this->service->create($this->collectiveId, [3, 1], $this->userId);
	}

	private function expectValidCollectiveWithPages(string $name = 'My Collective'): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true, name: $name));
		$this->pageService->method('findAll')->willReturn([$this->makePageInfo(1)]);
	}

	/**
	 * @dataProvider inProgressStatusProvider
	 */
	public function testPublishRejectsWhileStaticSiteOfCollectiveIsInProgress(string $status): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByCollectiveId')
			->with($this->collectiveId)
			->willReturn([$this->makeStaticSite($status)]);

		$this->expectException(StaticSitePublicationInProgressException::class);
		$this->staticSiteMapper->expects($this->never())->method('create');

		$this->service->create($this->collectiveId, [1], $this->userId, 'Title', 'other-site');
	}

	public function testPublishIgnoresAbortedPublicationOfCollective(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByCollectiveId')
			->willReturn([$this->makeStaticSite(StaticSite::STATUS_PENDING, StaticSite::PENDING_TIMEOUT + 1)]);

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));

		$this->service->create($this->collectiveId, [1], $this->userId, 'Title', 'other-site');
	}

	public function testPublishUsesGivenTitleSlugAndTheme(): void {
		$this->expectValidCollectiveWithPages();

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [1], 'Our Site', 'our-site-2026', $this->userId, StaticSite::THEME_TOPNAV)
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));

		$this->service->create($this->collectiveId, [1], $this->userId, '  Our Site ', 'our-site-2026', StaticSite::THEME_TOPNAV);
	}

	public function testPublishDefaultSlugIsTruncated(): void {
		$name = str_repeat('a', 70);
		$this->expectValidCollectiveWithPages($name);

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [1], $name, str_repeat('a', 64), $this->userId, StaticSite::THEME_WIKI)
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));

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

	public static function invalidThemeProvider(): array {
		return [
			'empty' => [''],
			'unknown' => ['topbar'],
			'uppercase' => ['Wiki'],
		];
	}

	/**
	 * @dataProvider invalidThemeProvider
	 */
	public function testPublishRejectsInvalidTheme(string $theme): void {
		$this->expectValidCollectiveWithPages();

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('create');

		$this->service->create($this->collectiveId, [1], $this->userId, null, null, $theme);
	}

	public function testPublishThrowsWhenSlugAlreadyTaken(): void {
		$this->expectValidCollectiveWithPages();

		$dbException = $this->createMock(DBException::class);
		$dbException->method('getReason')->willReturn(DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
		$this->staticSiteMapper->method('create')->willThrowException($dbException);

		$this->expectException(StaticSiteSlugTakenException::class);

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
			->method('startBuild')
			->with($staticSite, $this->isNewBuildId(), 'New Title', StaticSite::THEME_WIKI, [1])
			->willReturn(true);
		$this->archiver->expects($this->once())
			->method('store')
			->with($this->staticSiteId, $this->isNewBuildId());
		$this->generatorClient->expects($this->once())
			->method('create')
			->with($this->callback(fn (StaticSite $built): bool => $built->getId() === 7 && $built->getBuildId() !== $this->buildId), 'New Title', StaticSite::THEME_WIKI);

		$provided = $this->makeStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->once())
			->method('updateRequestStatus')
			->with($this->callback(fn (StaticSite $built): bool => $built->getTitle() === 'New Title'
				&& $built->getSelectedPageIds() === [1]
				&& $built->getBuildId() !== $this->buildId), StaticSite::STATUS_PROVIDED)
			->willReturn(true);
		$this->staticSiteMapper->method('find')->with(7)->willReturn($provided);

		$result = $this->service->update($this->collectiveId, 7, ['1'], ' New Title ', $this->userId);

		$this->assertSame($provided, $result);
	}

	public function testUpdateUpdatesPublishedStaticSiteAtGenerator(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_FAILED);
		$staticSite->setPublishedUrl('https://example.org/my-collective/');
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);

		$this->generatorClient->expects($this->once())->method('update')->with($staticSite, 'Title', StaticSite::THEME_WIKI);
		$this->generatorClient->expects($this->never())->method('create');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateCreatesNeverPublishedStaticSiteAtGenerator(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_FAILED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);

		$this->generatorClient->expects($this->once())->method('create')->with($staticSite, 'Title', StaticSite::THEME_WIKI);
		$this->generatorClient->expects($this->never())->method('update');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRepublishesAbortedPublication(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PENDING, StaticSite::PENDING_TIMEOUT + 1);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->expects($this->once())->method('startBuild')->willReturn(true);
		$this->staticSiteMapper->expects($this->once())->method('updateRequestStatus');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public static function inProgressStatusProvider(): array {
		return [
			'pending' => [StaticSite::STATUS_PENDING],
			'provided' => [StaticSite::STATUS_PROVIDED],
		];
	}

	/**
	 * @dataProvider inProgressStatusProvider
	 */
	public function testUpdateRejectsStaticSiteInProgress(string $status): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite($status);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('findByCollectiveId')->willReturn([$staticSite]);

		$this->expectException(StaticSitePublicationInProgressException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	/**
	 * @dataProvider inProgressStatusProvider
	 */
	public function testUpdateRejectsWhileOtherStaticSiteOfCollectiveIsInProgress(string $status): void {
		$this->expectValidCollectiveWithPages();
		$otherStaticSite = $this->makeStaticSite($status);
		$otherStaticSite->setId(8);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->method('findByCollectiveId')
			->with($this->collectiveId)
			->willReturn([$otherStaticSite]);

		$this->expectException(StaticSitePublicationInProgressException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRejectsPublicationStartedConcurrently(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->method('startBuild')->willReturn(false);

		$this->expectException(UnprocessableEntityException::class);
		$this->archiver->expects($this->never())->method('store');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRestoresStatusIfArchiveCannotBeProvided(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);
		$this->archiver->method('store')->willThrowException(new ServiceException('Failed'));

		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($this->callback(fn (StaticSite $previous): bool => $previous->getBuildId() === $this->buildId
				&& $previous->getStatus() === StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->expects($this->never())->method('updateRequestStatus');
		// Only the possibly partially stored archive of the new build is deleted
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId, $this->isNewBuildId());
		$this->archiver->expects($this->never())->method('deleteAll');
		$this->archiver->expects($this->never())->method('deleteAllExcept');

		$this->expectException(ServiceException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRestoresAbortedPublicationIfArchiveCannotBeProvided(): void {
		$this->expectValidCollectiveWithPages();
		$updatedAt = $this->now - StaticSite::PENDING_TIMEOUT - 1;
		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING, StaticSite::PENDING_TIMEOUT + 1));
		$this->staticSiteMapper->method('startBuild')->willReturn(true);
		$this->archiver->method('store')->willThrowException(new ServiceException('Failed'));

		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($this->callback(fn (StaticSite $previous): bool => $previous->getStatus() === StaticSite::STATUS_PENDING
				&& $previous->getUpdatedAt() === $updatedAt
				&& !$previous->isInProgress($this->now)));

		$this->expectException(ServiceException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateIgnoresDeletedStaticSiteWhenRestoringStatus(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->method('startBuild')->willReturn(true);
		$this->archiver->method('store')->willThrowException(new ServiceException('Failed'));
		$this->staticSiteMapper->method('restoreStatus')
			->willThrowException(new NotFoundException('Static site not found'));

		$this->expectException(ServiceException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRestoresStatusAndKeepsMessageIfPathIsTooLong(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);
		$this->archiver->method('store')->willThrowException(new UnprocessableEntityException('The path is too long'));

		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($this->callback(fn (StaticSite $previous): bool => $previous->getBuildId() === $this->buildId));

		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('The path is too long');
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenNoPageIdsGiven(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenUserCannotEdit(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: false));

		$this->expectException(NotPermittedException::class);
		$this->staticSiteMapper->expects($this->never())->method('findByIdAndCollectiveId');
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenStaticSiteNotInCollective(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->willThrowException(new NotFoundException('Static site not found'));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	/**
	 * @dataProvider invalidTitleProvider
	 */
	public function testUpdateRejectsInvalidTitle(string $title): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1], $title, $this->userId);
	}

	public function testUpdateChangesSlugOfUnpublishedStaticSite(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_FAILED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);

		$this->staticSiteMapper->expects($this->once())
			->method('startBuild')
			->with($staticSite, $this->isNewBuildId(), 'Title', StaticSite::THEME_WIKI, [1], 'new-slug')
			->willReturn(true);
		$this->generatorClient->expects($this->once())
			->method('create')
			->with($this->callback(fn (StaticSite $built): bool => $built->getSlug() === 'new-slug'), 'Title', StaticSite::THEME_WIKI);

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, 'new-slug');
	}

	public function testUpdateKeepsSlugOfPublishedStaticSite(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$staticSite->setPublishedUrl('https://example.org/my-collective/');
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);

		$this->staticSiteMapper->expects($this->once())
			->method('startBuild')
			->with($staticSite, $this->isNewBuildId(), 'Title', StaticSite::THEME_WIKI, [1], null)
			->willReturn(true);

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, 'my-collective');
	}

	public function testUpdateRejectsSlugChangeOfPublishedStaticSite(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_FAILED);
		$staticSite->setPublishedUrl('https://example.org/my-collective/');
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, 'new-slug');
	}

	public function testUpdateChangesThemeOfPublishedStaticSite(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$staticSite->setPublishedUrl('https://example.org/my-collective/');
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);

		$this->generatorClient->expects($this->once())
			->method('update')
			->with($staticSite, 'Title', StaticSite::THEME_TOPNAV);

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, null, StaticSite::THEME_TOPNAV);
	}

	public function testUpdateKeepsCurrentThemeIfNoneGiven(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_FAILED);
		$staticSite->setTheme(StaticSite::THEME_TOPNAV);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);

		$this->generatorClient->expects($this->once())
			->method('create')
			->with($staticSite, 'Title', StaticSite::THEME_TOPNAV);

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	/**
	 * @dataProvider invalidThemeProvider
	 */
	public function testUpdateRejectsInvalidTheme(string $theme): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_FAILED));

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, null, $theme);
	}

	/**
	 * @dataProvider invalidSlugProvider
	 */
	public function testUpdateRejectsInvalidSlug(string $slug): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_FAILED));

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, $slug);
	}

	public function testUpdateThrowsWhenSlugAlreadyTaken(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_FAILED));

		$dbException = $this->createMock(DBException::class);
		$dbException->method('getReason')->willReturn(DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
		$this->staticSiteMapper->method('startBuild')->willThrowException($dbException);

		$this->expectException(StaticSiteSlugTakenException::class);
		$this->archiver->expects($this->never())->method('store');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, 'taken');
	}

	public function testUpdateRethrowsOtherDatabaseErrorsWhenChangingSlug(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_FAILED));

		$dbException = $this->createMock(DBException::class);
		$dbException->method('getReason')->willReturn(DBException::REASON_DRIVER);
		$this->staticSiteMapper->method('startBuild')->willThrowException($dbException);

		$this->expectException(DBException::class);

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, 'new-slug');
	}

	public function testUpdateRestoresPreviousSlugIfArchiveCannotBeProvided(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_FAILED));
		$this->staticSiteMapper->method('startBuild')->willReturn(true);
		$this->archiver->method('store')->willThrowException(new ServiceException('Failed'));

		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($this->callback(fn (StaticSite $previous): bool => $previous->getSlug() === 'my-collective'
				&& $previous->getStatus() === StaticSite::STATUS_FAILED));

		$this->expectException(ServiceException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId, 'new-slug');
	}

	public function testUpdateProvidesArchiveForNewPageSelectionNotThePreviouslyStoredOne(): void {
		$collective = $this->makeCollective(canEdit: true);
		$this->collectiveService->method('getCollective')->willReturn($collective);
		$this->pageService->method('findAll')->willReturn([
			$this->makePageInfo(1),
			$this->makePageInfo(2),
		]);

		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$staticSite->setSelectedPageIds([1]);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);

		$this->contentCollector->expects($this->once())
			->method('collect')
			->with($this->collectiveId, [2, 1])
			->willReturn([]);

		// The page selection just changed from [1] to [2, 1]
		$this->service->update($this->collectiveId, 7, [2, 1], 'Title', $this->userId);
	}

	public function testUpdateRejectsPageIdsFromOtherCollective(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->service->update($this->collectiveId, 7, [1, 99], 'Title', $this->userId);
	}

	// --- delete() ---

	/**
	 * @dataProvider finishedStatusProvider
	 */
	public function testDeleteRemovesStaticSite(string $status): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$staticSite = $this->makeStaticSite($status);

		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->with(7, $this->collectiveId)
			->willReturn($staticSite);
		$this->remover->expects($this->once())->method('remove')->with($staticSite);

		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	public static function deleteInProgressStatusProvider(): array {
		return [
			'pending' => [StaticSite::STATUS_PENDING],
			'provided' => [StaticSite::STATUS_PROVIDED],
			'deleting' => [StaticSite::STATUS_DELETING],
		];
	}

	/**
	 * @dataProvider deleteInProgressStatusProvider
	 */
	public function testDeleteRejectsStaticSiteInProgress(string $status): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$staticSite = $this->makeStaticSite($status);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('findByCollectiveId')->willReturn([$staticSite]);
		$this->remover->expects($this->never())->method('remove');

		$this->expectException(StaticSitePublicationInProgressException::class);
		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	/**
	 * @dataProvider deleteInProgressStatusProvider
	 */
	public function testDeleteRejectsWhileOtherStaticSiteOfCollectiveIsInProgress(string $status): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$otherStaticSite = $this->makeStaticSite($status);
		$otherStaticSite->setId(8);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->method('findByCollectiveId')
			->with($this->collectiveId)
			->willReturn([$otherStaticSite]);
		$this->remover->expects($this->never())->method('remove');

		$this->expectException(StaticSitePublicationInProgressException::class);
		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	public function testDeleteRemovesAbortedStaticSite(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_DELETING, StaticSite::PENDING_TIMEOUT + 1);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('findByCollectiveId')->willReturn([$staticSite]);
		$this->remover->expects($this->once())->method('remove');

		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	public function testDeleteThrowsWhenUserCannotEdit(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: false));

		$this->expectException(NotPermittedException::class);
		$this->staticSiteMapper->expects($this->never())->method('findByIdAndCollectiveId');
		$this->remover->expects($this->never())->method('remove');

		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	public function testDeleteThrowsWhenStaticSiteNotInCollective(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->willThrowException(new NotFoundException('Static site not found'));

		$this->expectException(NotFoundException::class);
		$this->remover->expects($this->never())->method('remove');

		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	// --- archive ---

	private function expectCreatedStaticSite(): StaticSite {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PENDING);
		$this->staticSiteMapper->method('create')->willReturn($staticSite);
		return $staticSite;
	}

	public function testPublishRemovesStaticSiteIfArchiveCannotBeStored(): void {
		$staticSite = $this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn([]);
		$this->archiver->method('store')->willThrowException(new ServiceException('Failed'));

		$this->staticSiteMapper->expects($this->once())->method('delete')->with($staticSite);
		$this->remover->expects($this->once())->method('deleteArchives')->with($staticSite);
		$this->staticSiteMapper->expects($this->never())->method('updateRequestStatus');

		$this->expectException(ServiceException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishRemovesStaticSiteAndKeepsMessageIfPathIsTooLong(): void {
		$staticSite = $this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn([]);
		$exception = new UnprocessableEntityException('The path is too long');
		$this->archiver->method('store')->willThrowException($exception);

		$this->staticSiteMapper->expects($this->once())->method('delete')->with($staticSite);

		try {
			$this->service->create($this->collectiveId, [1], $this->userId);
			$this->fail('Expected UnprocessableEntityException');
		} catch (UnprocessableEntityException $e) {
			$this->assertSame($exception, $e);
		}
	}

	private function makeFileWithSize(int $size): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getSize')->willReturn($size);
		return $file;
	}

	public function testPublishAcceptsContentUpToMaxSize(): void {
		$this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn([
			'Readme.md' => $this->makeFileWithSize(ConfigLexicon::STATIC_SITE_MAX_SIZE_DEFAULT - 1),
			'image.png' => $this->makeFileWithSize(1),
		]);
		$this->archiver->expects($this->once())->method('store');

		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishRejectsContentAboveMaxSizeAndNamesLargestFiles(): void {
		$staticSite = $this->expectCreatedStaticSite();
		$mb = 1024 * 1024;
		$this->contentCollector->method('collect')->willReturn([
			'Readme.md' => $this->makeFileWithSize(1),
			'.attachments.1/video.mp4' => $this->makeFileWithSize(80 * $mb),
			'2024' => $this->makeFileWithSize(5 * $mb),
			'.attachments.2/photo.jpg' => $this->makeFileWithSize(20 * $mb),
		]);
		$this->archiver->expects($this->never())->method('store');
		$this->staticSiteMapper->expects($this->once())->method('delete')->with($staticSite);

		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('The website would be 105 MB, but at most 100 MB are allowed. Please remove or shrink large files, e.g. .attachments.1/video.mp4 (80 MB), .attachments.2/photo.jpg (20 MB), 2024 (5 MB)');
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishUsesConfiguredMaxSize(): void {
		$this->maxSize = 10;
		$this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn(['Readme.md' => $this->makeFileWithSize(11)]);
		$this->archiver->expects($this->never())->method('store');

		$this->expectException(UnprocessableEntityException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishRemovesStaticSiteIfContentCannotBeCollected(): void {
		$staticSite = $this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willThrowException(new FilesNotFoundException());
		$this->archiver->expects($this->never())->method('store');

		$this->staticSiteMapper->expects($this->once())->method('delete')->with($staticSite);

		$this->expectException(ServiceException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishDeletesArchivesIfStaticSiteWasDeletedMeanwhile(): void {
		$staticSite = $this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn([]);
		$this->staticSiteMapper->method('find')
			->willThrowException(new DoesNotExistException('Static site not found'));
		$this->remover->expects($this->once())->method('deleteArchives')->with($staticSite);

		$this->expectException(NotFoundException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	// --- publish feature settings ---

	public function testPublishRejectsIfPublishFeatureIsDisabled(): void {
		$this->publishFeatureEnabled = false;
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->expects($this->never())->method('create');
		$this->archiver->expects($this->never())->method('store');
		$this->generatorClient->expects($this->never())->method('create');

		$this->expectException(NotPermittedException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testUpdateRejectsIfPublishFeatureIsDisabled(): void {
		$this->publishFeatureEnabled = false;
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->expects($this->never())->method('startBuild');
		$this->archiver->expects($this->never())->method('store');
		$this->generatorClient->expects($this->never())->method('create');

		$this->expectException(NotPermittedException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testDeleteRemovesStaticSiteIfPublishFeatureIsDisabled(): void {
		$this->publishFeatureEnabled = false;
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);

		$this->remover->expects($this->once())->method('remove')->with($staticSite);

		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	public function testGetStaticSitesReturnsListIfPublishFeatureIsDisabled(): void {
		$this->publishFeatureEnabled = false;
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: false));
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$this->staticSiteMapper->method('findByCollectiveId')->willReturn([$staticSite]);

		self::assertSame([$staticSite], $this->service->getStaticSites($this->collectiveId, $this->userId));
	}

	// --- static site generator ---

	public function testPublishRejectsIfGeneratorIsNotConfigured(): void {
		$this->expectValidCollectiveWithPages();
		$this->generatorClient->method('assertConfigured')
			->willThrowException(new StaticSiteGeneratorNotConfiguredException('Not configured'));
		$this->staticSiteMapper->expects($this->never())->method('create');
		$this->archiver->expects($this->never())->method('store');

		$this->expectException(StaticSiteGeneratorNotConfiguredException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public static function generatorFailureProvider(): array {
		return [
			'rejected' => [new StaticSiteGeneratorException('Rejected'), StaticSite::ERROR_GENERATOR_REJECTED, true],
			'unavailable' => [new StaticSiteGeneratorUnavailableException('Unavailable'), StaticSite::ERROR_GENERATOR_UNAVAILABLE, false],
		];
	}

	/**
	 * The archive of an unavailable generator is kept, as the generator may have accepted the build anyway
	 *
	 * @dataProvider generatorFailureProvider
	 */
	public function testPublishKeepsStaticSiteAsFailedIfGeneratorDoesNotAcceptBuild(StaticSiteGeneratorException $exception, string $lastError, bool $deletesArchive): void {
		$staticSite = $this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn(['Readme.md' => $this->makeFileWithSize(1)]);
		$this->generatorClient->method('create')->willThrowException($exception);

		$this->staticSiteMapper->expects($this->never())->method('delete');
		$this->archiver->expects($deletesArchive ? $this->once() : $this->never())->method('delete')->with($this->staticSiteId, $this->buildId);
		$this->staticSiteMapper->expects($this->once())
			->method('updateRequestStatus')
			->with($staticSite, StaticSite::STATUS_FAILED, $lastError);

		$this->expectException($exception::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishValidatesInputBeforeCheckingGeneratorConfiguration(): void {
		$this->expectValidCollectiveWithPages();
		$this->generatorClient->expects($this->never())->method('assertConfigured');

		$this->expectException(UnprocessableEntityException::class);
		$this->service->create($this->collectiveId, [1], $this->userId, null, 'Invalid Slug');
	}

	public function testUpdateRejectsIfGeneratorIsNotConfigured(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->generatorClient->method('assertConfigured')
			->willThrowException(new StaticSiteGeneratorNotConfiguredException('Not configured'));
		$this->staticSiteMapper->expects($this->never())->method('startBuild');

		$this->expectException(StaticSiteGeneratorNotConfiguredException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateFindsStaticSiteBeforeCheckingGeneratorConfiguration(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->willThrowException(new NotFoundException('Static site not found'));
		$this->generatorClient->expects($this->never())->method('assertConfigured');

		$this->expectException(NotFoundException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateStoresStatusFailedIfGeneratorDoesNotAcceptBuild(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);
		$this->generatorClient->method('create')->willThrowException(new StaticSiteGeneratorException('Rejected'));

		$this->staticSiteMapper->expects($this->once())
			->method('updateRequestStatus')
			->with($staticSite, StaticSite::STATUS_FAILED, StaticSite::ERROR_GENERATOR_REJECTED);
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId, $this->isNewBuildId());

		$this->expectException(StaticSiteGeneratorException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateStoresStatusFailedIfGeneratorIsUnavailable(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('startBuild')->willReturn(true);
		$this->generatorClient->method('create')->willThrowException(new StaticSiteGeneratorUnavailableException('Unavailable'));

		$this->staticSiteMapper->expects($this->once())
			->method('updateRequestStatus')
			->with($staticSite, StaticSite::STATUS_FAILED, StaticSite::ERROR_GENERATOR_UNAVAILABLE);
		$this->archiver->expects($this->never())->method('delete');

		$this->expectException(StaticSiteGeneratorUnavailableException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
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

	// --- getArchive() ---

	public function testGetArchiveReturnsArchiveOfCurrentBuild(): void {
		$archive = $this->createMock(ISimpleFile::class);
		$this->staticSiteMapper->method('findOneByStaticSiteId')
			->with($this->staticSiteId)
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PROVIDED));
		$this->archiver->method('get')->with($this->staticSiteId, $this->buildId)->willReturn($archive);

		$this->assertSame($archive, $this->service->getArchive($this->staticSiteId, $this->buildId));
	}

	public static function notCurrentBuildIdProvider(): array {
		return [
			'older build' => ['01980000-0000-7000-8000-000000000000'],
			'invalid' => ['..'],
		];
	}

	/**
	 * @dataProvider notCurrentBuildIdProvider
	 */
	public function testGetArchiveThrowsForBuildThatIsNotTheCurrentOne(string $buildId): void {
		$this->staticSiteMapper->method('findOneByStaticSiteId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PROVIDED));
		$this->archiver->expects($this->never())->method('get');

		$this->expectException(StaticSiteBuildNotFoundException::class);
		$this->service->getArchive($this->staticSiteId, $buildId);
	}

	public function testGetArchiveThrowsIfStaticSiteDoesNotExist(): void {
		$this->staticSiteMapper->method('findOneByStaticSiteId')
			->willThrowException(new DoesNotExistException('Not found'));
		$this->archiver->expects($this->never())->method('get');

		try {
			$this->service->getArchive($this->staticSiteId, $this->buildId);
			$this->fail('Expected NotFoundException');
		} catch (NotFoundException $e) {
			$this->assertNotInstanceOf(StaticSiteBuildNotFoundException::class, $e);
		}
	}

	public function testGetArchiveThrowsIfArchiveOfCurrentBuildDoesNotExist(): void {
		$this->staticSiteMapper->method('findOneByStaticSiteId')
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));
		$this->archiver->method('get')->willThrowException(new NotFoundException('Static site archive not found'));

		$this->expectException(StaticSiteBuildNotFoundException::class);
		$this->service->getArchive($this->staticSiteId, $this->buildId);
	}

	// --- updateStatus() ---

	private string $olderBuildId = '01980000-0000-7000-8000-000000000000';

	private function isCurrentBuild(): Callback {
		return $this->callback(fn (StaticSite $staticSite): bool => $staticSite->getId() === 7
			&& $staticSite->getBuildId() === $this->buildId);
	}

	private function expectStaticSite(string $status, ?string $publishedBuildId = null): StaticSite {
		$staticSite = $this->makeStaticSite($status);
		$staticSite->setPublishedBuildId($publishedBuildId);
		$this->staticSiteMapper->method('findOneByStaticSiteId')
			->with($this->staticSiteId)
			->willReturn($staticSite);
		return $staticSite;
	}

	public static function unpublishedStatusProvider(): array {
		return [
			'pending, callback before request concluded' => [StaticSite::STATUS_PENDING],
			'provided' => [StaticSite::STATUS_PROVIDED],
			'failed, e.g. after a timeout' => [StaticSite::STATUS_FAILED],
		];
	}

	/**
	 * @dataProvider unpublishedStatusProvider
	 */
	public function testUpdateStatusStoresPublicationOfCurrentBuildAndDeletesItsArchive(string $currentStatus): void {
		$this->expectStaticSite($currentStatus);
		$this->staticSiteMapper->expects($this->once())
			->method('updateStatusPublished')
			->with($this->isCurrentBuild(), 'https://publish.example.org/my-site/')
			->willReturn(true);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusFailed');
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId, $this->buildId);

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_PUBLISHED, ['publish_url' => 'https://publish.example.org/my-site/']);
	}

	/**
	 * @dataProvider unpublishedStatusProvider
	 */
	public function testUpdateStatusStoresFailureOfCurrentBuildAndLogsErrorMessage(string $currentStatus): void {
		$this->expectStaticSite($currentStatus);
		$this->staticSiteMapper->expects($this->once())
			->method('updateStatusFailed')
			->with($this->isCurrentBuild(), StaticSite::ERROR_GENERATOR_FAILED)
			->willReturn(true);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusPublished');
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId, $this->buildId);
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('something went wrong'));

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_FAILED, ['error_message' => 'something went wrong']);
	}

	public static function invalidPublishUrlProvider(): array {
		return [
			'missing' => [[]],
			'not a string' => [['publish_url' => ['https://example.org']]],
			'javascript scheme' => [['publish_url' => 'javascript:alert(1)']],
			'without host' => [['publish_url' => 'https://']],
			'too long' => [['publish_url' => 'https://example.org/' . str_repeat('a', 236)]],
		];
	}

	/**
	 * @dataProvider invalidPublishUrlProvider
	 */
	public function testUpdateStatusStoresPublishedWithoutValidUrlAsFailed(array $data): void {
		$this->expectStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusPublished');
		$this->staticSiteMapper->expects($this->once())
			->method('updateStatusFailed')
			->with($this->isCurrentBuild(), StaticSite::ERROR_GENERATOR_INVALID_URL)
			->willReturn(true);
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId, $this->buildId);
		$this->logger->expects($this->once())
			->method('warning')
			->with('Static site generator reported an invalid publish_url');

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_PUBLISHED, $data);
	}

	public function testUpdateStatusAcceptsPublishUrlUpToMaxLength(): void {
		$url = 'https://example.org/' . str_repeat('a', 235);
		$this->expectStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->once())
			->method('updateStatusPublished')
			->with($this->isCurrentBuild(), $url)
			->willReturn(true);

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_PUBLISHED, ['publish_url' => $url]);
	}

	public static function callbackProvider(): array {
		return [
			'published' => ['updateStatusPublished', StaticSite::STATUS_PUBLISHED, ['publish_url' => 'https://example.org']],
			'published with invalid URL' => ['updateStatusFailed', StaticSite::STATUS_PUBLISHED, []],
			'failed' => ['updateStatusFailed', StaticSite::STATUS_FAILED, []],
		];
	}

	/**
	 * The build got published already or another build started between reading and updating
	 *
	 * @dataProvider callbackProvider
	 */
	public function testUpdateStatusLogsIfNotConcluded(string $mapperMethod, string $status, array $data): void {
		$this->expectStaticSite(StaticSite::STATUS_PUBLISHED, $this->buildId);
		$this->staticSiteMapper->expects($this->once())->method($mapperMethod)->willReturn(false);
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId, $this->buildId);
		$this->logger->expects($this->atLeastOnce())
			->method('warning')
			->with($this->logicalOr(
				'Static site generator reported an invalid publish_url',
				'Ignoring static site status callback, the build got published already or another build started meanwhile',
			));

		$this->service->updateStatus($this->staticSiteId, $this->buildId, $status, $data);
	}

	public function testUpdateStatusOfOlderBuildIsOnlyLogged(): void {
		$this->expectStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusPublished');
		$this->staticSiteMapper->expects($this->never())->method('updateStatusFailed');
		$this->archiver->expects($this->never())->method('delete');
		$this->logger->expects($this->never())->method('warning');
		$this->logger->expects($this->once())
			->method('info')
			->with('Ignoring static site status callback of a build that is not the current one');

		$this->service->updateStatus($this->staticSiteId, $this->olderBuildId, StaticSite::STATUS_FAILED);
	}

	/**
	 * Without archive, e.g. as a newer build replaced it, the build is still known as older one
	 */
	public function testUpdateStatusOfLatePublishedOlderBuildIsLoggedAsWarning(): void {
		$this->expectStaticSite(StaticSite::STATUS_PUBLISHED, $this->buildId);
		$this->archiver->expects($this->never())->method('get');
		$this->staticSiteMapper->expects($this->never())->method('updateStatusPublished');
		$this->logger->expects($this->once())
			->method('warning')
			->with('Static site generator published a build that is not the current one anymore');

		$this->service->updateStatus($this->staticSiteId, $this->olderBuildId, StaticSite::STATUS_PUBLISHED, ['publish_url' => 'https://example.org']);
	}

	public function testUpdateStatusIgnoresDuplicateCallbackOfPublishedBuild(): void {
		$this->expectStaticSite(StaticSite::STATUS_PENDING, $this->olderBuildId);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusPublished');
		$this->logger->expects($this->never())->method('warning');

		$this->service->updateStatus($this->staticSiteId, $this->olderBuildId, StaticSite::STATUS_PUBLISHED, ['publish_url' => 'https://example.org']);
	}

	public static function unknownBuildIdProvider(): array {
		return [
			'newer than the current build' => ['019a0000-0000-7000-8000-000000000000'],
			'invalid' => ['..'],
		];
	}

	/**
	 * @dataProvider unknownBuildIdProvider
	 */
	public function testUpdateStatusThrowsForUnknownBuild(string $buildId): void {
		$this->expectStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusFailed');

		$this->expectException(StaticSiteBuildNotFoundException::class);
		$this->service->updateStatus($this->staticSiteId, $buildId, StaticSite::STATUS_FAILED);
	}

	public function testUpdateStatusIgnoresCallbackWithMismatchingBuildIdInBody(): void {
		$this->expectStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusPublished');
		$this->archiver->expects($this->never())->method('delete');
		$this->logger->expects($this->once())
			->method('warning')
			->with('Ignoring static site status callback, its build_id does not match the callback URL');

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_PUBLISHED, ['publish_url' => 'https://example.org', 'build_id' => $this->olderBuildId]);
	}

	public function testUpdateStatusAcceptsMatchingBuildIdInBody(): void {
		$this->expectStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->once())->method('updateStatusPublished')->willReturn(true);

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_PUBLISHED, ['publish_url' => 'https://example.org', 'build_id' => $this->buildId]);
	}

	public function testUpdateStatusThrowsWhenStaticSiteNotFound(): void {
		$this->staticSiteMapper->method('findOneByStaticSiteId')
			->willThrowException(new DoesNotExistException('not found'));
		$this->staticSiteMapper->expects($this->never())->method('updateStatusFailed');

		try {
			$this->service->updateStatus('unknown-uuid', $this->buildId, StaticSite::STATUS_FAILED);
			$this->fail('Expected NotFoundException');
		} catch (NotFoundException $e) {
			$this->assertNotInstanceOf(StaticSiteBuildNotFoundException::class, $e);
		}
	}

	public function testUpdateStatusThrowsForInvalidStatus(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('findOneByStaticSiteId');

		$this->service->updateStatus($this->staticSiteId, $this->buildId, 'not-a-real-status');
	}

	public function testUpdateStatusThrowsForInProgressStatus(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('findOneByStaticSiteId');

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_PENDING);
	}

	public function testUpdateStatusRemovesDeletedStaticSite(): void {
		$staticSite = $this->expectStaticSite(StaticSite::STATUS_DELETING);
		$this->remover->expects($this->once())->method('removeDeleted')->with($staticSite);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusFailed');

		$this->service->updateStatus($this->staticSiteId, $this->buildId, 'deleted');
	}

	public function testUpdateStatusKeepsStaticSiteOnlineIfDeletionFailed(): void {
		$this->expectStaticSite(StaticSite::STATUS_DELETING);
		$this->staticSiteMapper->expects($this->once())
			->method('updateStatusFailed')
			->with($this->isCurrentBuild(), StaticSite::ERROR_GENERATOR_DELETE_FAILED)
			->willReturn(true);
		$this->remover->expects($this->never())->method('removeDeleted');
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('disk full'));

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_FAILED, ['error_message' => 'disk full']);
	}

	public function testUpdateStatusIgnoresPublishedWhileDeleting(): void {
		$this->expectStaticSite(StaticSite::STATUS_DELETING);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusPublished');
		$this->remover->expects($this->never())->method('removeDeleted');
		$this->logger->expects($this->once())
			->method('warning')
			->with('Ignoring static site status callback, the static site is being deleted');

		$this->service->updateStatus($this->staticSiteId, $this->buildId, StaticSite::STATUS_PUBLISHED, ['publish_url' => 'https://example.org']);
	}

	public function testUpdateStatusIgnoresDeletedWhileNotDeleting(): void {
		$this->expectStaticSite(StaticSite::STATUS_PROVIDED);
		$this->remover->expects($this->never())->method('removeDeleted');
		$this->staticSiteMapper->expects($this->never())->method('updateStatusFailed');
		$this->logger->expects($this->once())
			->method('warning')
			->with('Ignoring static site status callback, the static site is not being deleted');

		$this->service->updateStatus($this->staticSiteId, $this->buildId, 'deleted');
	}
}
