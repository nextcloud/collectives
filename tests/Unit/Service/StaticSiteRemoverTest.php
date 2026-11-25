<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Db\StaticSiteMapper;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\ServiceException;
use OCA\Collectives\Service\StaticSiteArchiver;
use OCA\Collectives\Service\StaticSiteGeneratorBusyException;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteGeneratorNotConfiguredException;
use OCA\Collectives\Service\StaticSiteGeneratorTimeoutException;
use OCA\Collectives\Service\StaticSiteGeneratorUnavailableException;
use OCA\Collectives\Service\StaticSiteRemover;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\DB\Exception as DBException;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Test\TestCase;

class StaticSiteRemoverTest extends TestCase {
	private StaticSiteMapper&MockObject $staticSiteMapper;
	private StaticSiteArchiver&MockObject $archiver;
	private StaticSiteGeneratorClient&MockObject $generatorClient;
	private LoggerInterface&MockObject $logger;
	private StaticSiteRemover $remover;

	private string $staticSiteId = '01990000-0000-7000-8000-000000000000';
	private string $buildId = '01990000-0000-7000-8000-000000000001';

	protected function setUp(): void {
		parent::setUp();

		$this->staticSiteMapper = $this->createMock(StaticSiteMapper::class);
		$this->archiver = $this->createMock(StaticSiteArchiver::class);
		$this->generatorClient = $this->createMock(StaticSiteGeneratorClient::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->remover = new StaticSiteRemover($this->staticSiteMapper, $this->archiver, $this->generatorClient, $this->logger);
	}

	private function makeStaticSite(bool $published): StaticSite {
		$staticSite = new StaticSite();
		$staticSite->setId(7);
		$staticSite->setStaticSiteId($this->staticSiteId);
		$staticSite->setBuildId($this->buildId);
		$staticSite->setStatus($published ? StaticSite::STATUS_PUBLISHED : StaticSite::STATUS_FAILED);
		if ($published) {
			$staticSite->setPublishedUrl('https://example.org/my-site/');
		}
		return $staticSite;
	}

	private function isNewBuildId(): Callback {
		return $this->callback(fn (string $buildId): bool => Uuid::isValid($buildId) && $buildId !== $this->buildId);
	}

	private function isDeletingStaticSite(): Callback {
		return $this->callback(fn (StaticSite $staticSite): bool => $staticSite->getStatus() === StaticSite::STATUS_DELETING
			&& $staticSite->getBuildId() !== $this->buildId);
	}

	/**
	 * @dataProvider publishedProvider
	 */
	public function testRemoveAsksGeneratorToDeleteStaticSite(bool $published): void {
		$staticSite = $this->makeStaticSite($published);
		$this->staticSiteMapper->expects($this->once())
			->method('startDeletion')
			->with($staticSite, $this->isNewBuildId())
			->willReturn(true);
		$this->generatorClient->expects($this->once())
			->method('delete')
			->with($this->isDeletingStaticSite())
			->willReturn(true);
		// The static site is deleted once the generator reports it as deleted
		$this->staticSiteMapper->expects($this->never())->method('delete');
		$this->staticSiteMapper->expects($this->never())->method('deleteIfCurrentBuild');
		$this->archiver->expects($this->once())->method('deleteAll')->with($this->staticSiteId);

		$this->assertFalse($this->remover->remove($staticSite));
	}

	public static function publishedProvider(): array {
		return [
			'published' => [true],
			// The generator may know it anyway, e.g. while its first build is running
			'never published' => [false],
		];
	}

	public function testRemoveDeletesStaticSiteUnknownToGeneratorRightAway(): void {
		$staticSite = $this->makeStaticSite(published: true);
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->method('delete')->willReturn(false);
		$this->staticSiteMapper->expects($this->once())
			->method('deleteIfCurrentBuild')
			->with($this->isDeletingStaticSite())
			->willReturn(true);
		$this->archiver->expects($this->once())->method('deleteAll');

		$this->assertTrue($this->remover->remove($staticSite));
	}

	public function testRemoveRejectsStaticSiteChangedMeanwhile(): void {
		$this->staticSiteMapper->method('startDeletion')->willReturn(false);
		$this->generatorClient->expects($this->never())->method('delete');

		$this->expectException(UnprocessableEntityException::class);
		$this->remover->remove($this->makeStaticSite(published: true));
	}

	public function testRemoveRequiresConfiguredGenerator(): void {
		$this->generatorClient->method('assertConfigured')
			->willThrowException(new StaticSiteGeneratorNotConfiguredException('Not configured'));
		$this->staticSiteMapper->expects($this->never())->method('startDeletion');

		$this->expectException(StaticSiteGeneratorNotConfiguredException::class);
		$this->remover->remove($this->makeStaticSite(published: true));
	}

	public static function notAcceptedExceptionProvider(): array {
		return [
			'rejected' => [new StaticSiteGeneratorException('Rejected')],
			'busy' => [new StaticSiteGeneratorBusyException('Busy')],
			'not reachable' => [new StaticSiteGeneratorUnavailableException('Unavailable')],
		];
	}

	/**
	 * @dataProvider notAcceptedExceptionProvider
	 */
	public function testRemoveRestoresStaticSiteIfGeneratorDoesNotAcceptRequest(StaticSiteGeneratorException $exception): void {
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->method('delete')->willThrowException($exception);
		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($this->callback(fn (StaticSite $previous): bool => $previous->getStatus() === StaticSite::STATUS_PUBLISHED
				&& $previous->getBuildId() === $this->buildId));
		$this->archiver->expects($this->never())->method('deleteAll');

		$this->expectException($exception::class);
		$this->remover->remove($this->makeStaticSite(published: true));
	}

	public function testRemoveIgnoresStaticSiteDeletedMeanwhileWhenRestoring(): void {
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->method('delete')->willThrowException(new StaticSiteGeneratorException('Rejected'));
		$this->staticSiteMapper->method('restoreStatus')->willThrowException(new NotFoundException('Static site not found'));

		$this->expectException(StaticSiteGeneratorException::class);
		$this->remover->remove($this->makeStaticSite(published: true));
	}

	/**
	 * The generator may have accepted the request anyway
	 */
	public function testRemoveKeepsStaticSiteDeletingIfGeneratorDidNotAnswer(): void {
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->method('delete')->willThrowException(new StaticSiteGeneratorTimeoutException('Timeout'));
		$this->staticSiteMapper->expects($this->never())->method('restoreStatus');
		$this->archiver->expects($this->never())->method('deleteAll');

		$this->expectException(StaticSiteGeneratorTimeoutException::class);
		$this->remover->remove($this->makeStaticSite(published: true));
	}

	public function testRemoveDeletedDeletesStaticSiteAndArchives(): void {
		$staticSite = $this->makeStaticSite(published: true);
		$this->staticSiteMapper->expects($this->once())->method('deleteIfCurrentBuild')->with($staticSite)->willReturn(true);
		$this->archiver->expects($this->once())->method('deleteAll')->with($this->staticSiteId);

		$this->assertTrue($this->remover->removeDeleted($staticSite));
	}

	public function testRemoveDeletedKeepsStaticSiteIfAnotherRequestStarted(): void {
		$this->staticSiteMapper->method('deleteIfCurrentBuild')->willReturn(false);
		$this->archiver->expects($this->never())->method('deleteAll');
		$this->logger->expects($this->once())->method('warning');

		$this->assertFalse($this->remover->removeDeleted($this->makeStaticSite(published: true)));
	}

	public function testRemoveSucceedsIfArchivesCannotBeDeleted(): void {
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->method('delete')->willReturn(false);
		$this->staticSiteMapper->expects($this->once())->method('deleteIfCurrentBuild')->willReturn(true);
		$this->archiver->method('deleteAll')->willThrowException(new ServiceException('Failed'));
		$this->logger->expects($this->once())->method('warning');

		$this->remover->remove($this->makeStaticSite(published: false));
	}

	public function testRemoveAllOfCollectiveRemovesEachStaticSite(): void {
		$neverPublished = $this->makeStaticSite(published: false);
		$published = $this->makeStaticSite(published: true);
		$published->setId(8);
		$this->staticSiteMapper->method('findByCollectiveId')->with(42)->willReturn([$neverPublished, $published]);
		$this->staticSiteMapper->expects($this->exactly(2))->method('startDeletion')->willReturn(true);
		$this->generatorClient->expects($this->exactly(2))->method('delete')->willReturn(true);

		$this->remover->removeAllOfCollective(42);
	}

	/**
	 * Purging a collective must not fail, the static site is kept to retry deleting it
	 */
	public function testRemoveAllOfCollectiveContinuesIfAStaticSiteCannotBeDeleted(): void {
		$first = $this->makeStaticSite(published: true);
		$second = $this->makeStaticSite(published: false);
		$second->setId(8);
		$this->staticSiteMapper->method('findByCollectiveId')->willReturn([$first, $second]);
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->expects($this->exactly(2))
			->method('delete')
			->willReturnOnConsecutiveCalls($this->throwException(new StaticSiteGeneratorUnavailableException('Unavailable')), true);
		$this->logger->expects($this->once())
			->method('warning')
			->with('Failed to delete static site of collective');

		$this->remover->removeAllOfCollective(42);
	}

	/**
	 * The generator may know a static site whose first publication is running, so it is asked to delete
	 * it. While the publication is running it rejects the request, and the deletion is retried later.
	 */
	public function testRemoveAllOfCollectiveAsksGeneratorToDeleteStaticSiteBeingPublishedFirstTime(): void {
		$staticSite = $this->makeStaticSite(published: false);
		$staticSite->setStatus(StaticSite::STATUS_PROVIDED);
		$this->staticSiteMapper->method('findByCollectiveId')->willReturn([$staticSite]);
		$this->staticSiteMapper->expects($this->once())->method('startDeletion')->with($staticSite)->willReturn(true);
		$this->generatorClient->expects($this->once())
			->method('delete')
			->willThrowException(new StaticSiteGeneratorException('Operation in progress'));
		$this->staticSiteMapper->expects($this->never())->method('delete');
		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($this->callback(fn (StaticSite $previous): bool => $previous->getStatus() === StaticSite::STATUS_PROVIDED));
		$this->logger->expects($this->once())
			->method('warning')
			->with('Failed to delete static site of collective');

		$this->remover->removeAllOfCollective(42);
	}

	public function testRemoveAllOfCollectiveDoesNotThrowIfStaticSitesCannotBeFound(): void {
		$this->staticSiteMapper->method('findByCollectiveId')->willThrowException($this->createMock(DBException::class));
		$this->logger->expects($this->once())->method('warning');

		$this->remover->removeAllOfCollective(42);
	}

	public function testRemoveAllOrphanedRetriesStaticSitesThatAreNotInProgress(): void {
		$now = 1800000000;
		$failed = $this->makeStaticSite(published: false);
		$failed->setUpdatedAt($now - 60);
		$deleting = $this->makeStaticSite(published: true);
		$deleting->setId(8);
		$deleting->setStatus(StaticSite::STATUS_DELETING);
		$deleting->setUpdatedAt($now - 60);
		$abortedDeleting = $this->makeStaticSite(published: true);
		$abortedDeleting->setId(9);
		$abortedDeleting->setStatus(StaticSite::STATUS_DELETING);
		$abortedDeleting->setUpdatedAt($now - StaticSite::PENDING_TIMEOUT - 1);
		$this->staticSiteMapper->method('findAllOrphaned')->willReturn([$failed, $deleting, $abortedDeleting]);

		$started = [];
		$this->staticSiteMapper->method('startDeletion')
			->willReturnCallback(static function (StaticSite $staticSite) use (&$started): bool {
				$started[] = $staticSite->getId();
				return true;
			});
		$this->generatorClient->method('delete')->willReturn(true);

		$this->remover->removeAllOrphaned($now);

		// The deletion in progress waits for its callback or timeout
		$this->assertSame([7, 9], $started);
	}

	public function testRemoveAllOrphanedContinuesIfAStaticSiteCannotBeDeleted(): void {
		$published = $this->makeStaticSite(published: true);
		$published->setUpdatedAt(0);
		$neverPublished = $this->makeStaticSite(published: false);
		$neverPublished->setId(8);
		$neverPublished->setUpdatedAt(0);
		$this->staticSiteMapper->method('findAllOrphaned')->willReturn([$published, $neverPublished]);
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->expects($this->exactly(2))
			->method('delete')
			->willReturnOnConsecutiveCalls($this->throwException(new StaticSiteGeneratorUnavailableException('Unavailable')), true);
		$this->logger->expects($this->once())
			->method('warning')
			->with('Failed to delete static site of collective');

		$this->remover->removeAllOrphaned(1800000000);
	}

	public function testRemoveAllOrphanedLogsOnceIfGeneratorIsNotConfigured(): void {
		$first = $this->makeStaticSite(published: true);
		$first->setUpdatedAt(0);
		$second = $this->makeStaticSite(published: false);
		$second->setId(8);
		$second->setUpdatedAt(0);
		$this->staticSiteMapper->method('findAllOrphaned')->willReturn([$first, $second]);
		$this->generatorClient->method('assertConfigured')
			->willThrowException(new StaticSiteGeneratorNotConfiguredException('Not configured'));
		$this->staticSiteMapper->expects($this->never())->method('startDeletion');
		$this->logger->expects($this->once())
			->method('warning')
			->with('Cannot delete orphaned static sites, the static site generator is not configured', ['count' => 2]);

		$this->remover->removeAllOrphaned(1800000000);
	}

	public function testRemoveAllOrphanedDoesNothingWithoutOrphanedStaticSites(): void {
		$this->staticSiteMapper->method('findAllOrphaned')->willReturn([]);
		$this->generatorClient->expects($this->never())->method('assertConfigured');
		$this->logger->expects($this->never())->method('warning');

		$this->remover->removeAllOrphaned(1800000000);
	}

	public function testRemoveAllOrphanedDoesNotThrowIfStaticSitesCannotBeFound(): void {
		$this->staticSiteMapper->method('findAllOrphaned')->willThrowException($this->createMock(DBException::class));
		$this->logger->expects($this->once())->method('warning');

		$this->remover->removeAllOrphaned(1800000000);
	}
}
