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
use OCA\Collectives\Service\StaticSiteContentCollector;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteGeneratorNotConfiguredException;
use OCA\Collectives\Service\StaticSiteService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DBException;
use OCP\Files\File;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Test\TestCase;

class StaticSiteServiceTest extends TestCase {
	private StaticSiteMapper&MockObject $staticSiteMapper;
	private CollectiveService&MockObject $collectiveService;
	private PageService&MockObject $pageService;
	private StaticSiteContentCollector&MockObject $contentCollector;
	private StaticSiteArchiver&MockObject $archiver;
	private StaticSiteGeneratorClient&MockObject $generatorClient;
	private LoggerInterface&MockObject $logger;
	private StaticSiteService $service;

	private string $userId = 'alice';
	private int $collectiveId = 42;
	private string $staticSiteId = '01990000-0000-7000-8000-000000000000';
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
		$staticSite->setSlug('my-collective');
		$staticSite->setStatus($status);
		return $staticSite;
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

		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PENDING);
		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, $pageIds, 'My Collective', 'my-collective', $this->userId)
			->willReturn($staticSite);

		$files = ['Readme.md' => $this->makeFileWithSize(1)];
		$this->contentCollector->expects($this->once())
			->method('collect')
			->with($this->collectiveId)
			->willReturn($files);
		$this->archiver->expects($this->once())
			->method('store')
			->with($this->staticSiteId, $files);
		$this->generatorClient->expects($this->once())
			->method('build')
			->with($staticSite, 'My Collective');

		$provided = $this->makeStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->once())
			->method('updateSettingsAndStatus')
			->with($staticSite, 'My Collective', $pageIds, StaticSite::STATUS_PROVIDED)
			->willReturn($provided);

		$result = $this->service->create($this->collectiveId, $pageIds, $this->userId);

		$this->assertSame($provided, $result);
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
			->with($this->collectiveId, [1, 2], $this->anything(), $this->anything(), $this->userId)
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

	private function expectValidCollectiveWithPages(string $name = 'My Collective'): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true, name: $name));
		$this->pageService->method('findAll')->willReturn([$this->makePageInfo(1)]);
	}

	public function testPublishUsesGivenTitleAndSlug(): void {
		$this->expectValidCollectiveWithPages();

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [1], 'Our Site', 'our-site-2026', $this->userId)
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));

		$this->service->create($this->collectiveId, [1], $this->userId, '  Our Site ', 'our-site-2026');
	}

	public function testPublishDefaultSlugIsTruncated(): void {
		$name = str_repeat('a', 70);
		$this->expectValidCollectiveWithPages($name);

		$this->staticSiteMapper->expects($this->once())
			->method('create')
			->with($this->collectiveId, [1], $name, str_repeat('a', 64), $this->userId)
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

	public static function finishedStatusProvider(): array {
		return [
			'provided' => [StaticSite::STATUS_PROVIDED],
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
			->method('updateStatusIfUnchanged')
			->with($staticSite, StaticSite::STATUS_PENDING)
			->willReturn(true);
		$this->archiver->expects($this->once())->method('store');
		$this->generatorClient->expects($this->once())
			->method('build')
			->with($staticSite, 'New Title');

		$provided = $this->makeStaticSite(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->expects($this->once())
			->method('updateSettingsAndStatus')
			->with($staticSite, 'New Title', [1], StaticSite::STATUS_PROVIDED)
			->willReturn($provided);

		$result = $this->service->update($this->collectiveId, 7, ['1'], ' New Title ', $this->userId);

		$this->assertSame($provided, $result);
	}

	public function testUpdateRepublishesAbortedPublication(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PENDING, StaticSite::PENDING_TIMEOUT + 1);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->expects($this->once())->method('updateStatusIfUnchanged')->willReturn(true);
		$this->staticSiteMapper->expects($this->once())->method('updateSettingsAndStatus');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRejectsStaticSiteInProgress(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusIfUnchanged');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRejectsPublicationStartedConcurrently(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->method('updateStatusIfUnchanged')->willReturn(false);

		$this->expectException(UnprocessableEntityException::class);
		$this->archiver->expects($this->never())->method('store');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRestoresStatusIfArchiveCannotBeProvided(): void {
		$this->expectValidCollectiveWithPages();
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);
		$this->staticSiteMapper->method('updateStatusIfUnchanged')->willReturn(true);
		$this->archiver->method('store')->willThrowException(new ServiceException('Failed'));

		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($staticSite);
		$this->staticSiteMapper->expects($this->never())->method('updateSettingsAndStatus');
		$this->archiver->expects($this->never())->method('delete');

		$this->expectException(ServiceException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateRestoresAbortedPublicationIfArchiveCannotBeProvided(): void {
		$this->expectValidCollectiveWithPages();
		$updatedAt = $this->now - StaticSite::PENDING_TIMEOUT - 1;
		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING, StaticSite::PENDING_TIMEOUT + 1));
		$this->staticSiteMapper->method('updateStatusIfUnchanged')->willReturn(true);
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
		$this->staticSiteMapper->method('updateStatusIfUnchanged')->willReturn(true);
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
		$this->staticSiteMapper->method('updateStatusIfUnchanged')->willReturn(true);
		$this->archiver->method('store')->willThrowException(new UnprocessableEntityException('The path is too long'));

		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($staticSite);

		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('The path is too long');
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenNoPageIdsGiven(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusIfUnchanged');

		$this->service->update($this->collectiveId, 7, [], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenUserCannotEdit(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: false));

		$this->expectException(NotPermittedException::class);
		$this->staticSiteMapper->expects($this->never())->method('findByIdAndCollectiveId');
		$this->staticSiteMapper->expects($this->never())->method('updateStatusIfUnchanged');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testUpdateThrowsWhenStaticSiteNotInCollective(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')
			->willThrowException(new NotFoundException('Static site not found'));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusIfUnchanged');

		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	/**
	 * @dataProvider invalidTitleProvider
	 */
	public function testUpdateRejectsInvalidTitle(string $title): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));

		$this->expectException(UnprocessableEntityException::class);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusIfUnchanged');

		$this->service->update($this->collectiveId, 7, [1], $title, $this->userId);
	}

	public function testUpdateRejectsPageIdsFromOtherCollective(): void {
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));

		$this->expectException(NotFoundException::class);
		$this->staticSiteMapper->expects($this->never())->method('updateStatusIfUnchanged');

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
		$this->staticSiteMapper->expects($this->once())
			->method('delete')
			->with($staticSite);
		$this->archiver->expects($this->once())
			->method('delete')
			->with($this->staticSiteId);

		$this->service->delete($this->collectiveId, 7, $this->userId);
	}

	public function testDeleteSucceedsIfArchiveDeletionFails(): void {
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->expects($this->once())->method('delete');
		$this->archiver->method('delete')->willThrowException(new ServiceException('Failed'));
		$this->logger->expects($this->once())->method('warning');

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
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId);
		$this->staticSiteMapper->expects($this->never())->method('updateSettingsAndStatus');

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

	public function testPublishDeletesArchiveIfStaticSiteWasDeletedMeanwhile(): void {
		$this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn([]);
		$this->staticSiteMapper->method('updateSettingsAndStatus')
			->willThrowException(new NotFoundException('Static site not found'));
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId);

		$this->expectException(NotFoundException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testPublishKeepsNotFoundExceptionIfArchiveDeletionFails(): void {
		$this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn([]);
		$this->staticSiteMapper->method('updateSettingsAndStatus')
			->willThrowException(new NotFoundException('Static site not found'));
		$this->archiver->method('delete')->willThrowException(new ServiceException('Failed'));
		$this->logger->expects($this->once())->method('warning');

		$this->expectException(NotFoundException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	// --- publish feature settings ---

	public function testPublishRejectsIfPublishFeatureIsDisabled(): void {
		$this->publishFeatureEnabled = false;
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->expects($this->never())->method('create');
		$this->archiver->expects($this->never())->method('store');
		$this->generatorClient->expects($this->never())->method('build');

		$this->expectException(NotPermittedException::class);
		$this->service->create($this->collectiveId, [1], $this->userId);
	}

	public function testUpdateRejectsIfPublishFeatureIsDisabled(): void {
		$this->publishFeatureEnabled = false;
		$this->expectValidCollectiveWithPages();
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($this->makeStaticSite(StaticSite::STATUS_PUBLISHED));
		$this->staticSiteMapper->expects($this->never())->method('updateStatusIfUnchanged');
		$this->archiver->expects($this->never())->method('store');
		$this->generatorClient->expects($this->never())->method('build');

		$this->expectException(NotPermittedException::class);
		$this->service->update($this->collectiveId, 7, [1], 'Title', $this->userId);
	}

	public function testDeleteRemovesStaticSiteIfPublishFeatureIsDisabled(): void {
		$this->publishFeatureEnabled = false;
		$this->collectiveService->method('getCollective')->willReturn($this->makeCollective(canEdit: true));
		$staticSite = $this->makeStaticSite(StaticSite::STATUS_PUBLISHED);
		$this->staticSiteMapper->method('findByIdAndCollectiveId')->willReturn($staticSite);

		$this->staticSiteMapper->expects($this->once())->method('delete')->with($staticSite);
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId);

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

	public function testPublishRemovesStaticSiteIfGeneratorDoesNotAcceptBuild(): void {
		$staticSite = $this->expectCreatedStaticSite();
		$this->contentCollector->method('collect')->willReturn(['Readme.md' => $this->makeFileWithSize(1)]);
		$this->generatorClient->method('build')->willThrowException(new StaticSiteGeneratorException('Rejected'));

		$this->staticSiteMapper->expects($this->once())->method('delete')->with($staticSite);
		$this->archiver->expects($this->once())->method('delete')->with($this->staticSiteId);
		$this->staticSiteMapper->expects($this->never())->method('updateSettingsAndStatus');

		$this->expectException(StaticSiteGeneratorException::class);
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
		$this->staticSiteMapper->expects($this->never())->method('updateStatusIfUnchanged');

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
		$this->staticSiteMapper->method('updateStatusIfUnchanged')->willReturn(true);
		$this->generatorClient->method('build')->willThrowException(new StaticSiteGeneratorException('Rejected'));

		$this->staticSiteMapper->expects($this->once())
			->method('updateSettingsAndStatus')
			->with($staticSite, 'Title', [1], StaticSite::STATUS_FAILED);
		$this->archiver->expects($this->never())->method('delete');

		$this->expectException(StaticSiteGeneratorException::class);
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

	public function testGetArchiveReturnsArchiveOfExistingStaticSite(): void {
		$archive = $this->createMock(ISimpleFile::class);
		$this->staticSiteMapper->method('findOneByStaticSiteId')
			->with($this->staticSiteId)
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PROVIDED));
		$this->archiver->method('get')->with($this->staticSiteId)->willReturn($archive);

		$this->assertSame($archive, $this->service->getArchive($this->staticSiteId));
	}

	public function testGetArchiveThrowsIfStaticSiteDoesNotExist(): void {
		$this->staticSiteMapper->method('findOneByStaticSiteId')
			->willThrowException(new DoesNotExistException('Not found'));
		$this->archiver->expects($this->never())->method('get');

		$this->expectException(NotFoundException::class);
		$this->service->getArchive($this->staticSiteId);
	}

	public function testGetArchiveThrowsIfArchiveDoesNotExist(): void {
		$this->staticSiteMapper->method('findOneByStaticSiteId')
			->willReturn($this->makeStaticSite(StaticSite::STATUS_PENDING));
		$this->archiver->method('get')->willThrowException(new NotFoundException('Static site archive not found'));

		$this->expectException(NotFoundException::class);
		$this->service->getArchive($this->staticSiteId);
	}
}
