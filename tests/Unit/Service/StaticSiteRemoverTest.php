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
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteGeneratorNotConfiguredException;
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

	public function testRemoveDeletesNeverPublishedStaticSiteRightAway(): void {
		$staticSite = $this->makeStaticSite(published: false);
		$this->generatorClient->expects($this->never())->method('delete');
		$this->staticSiteMapper->expects($this->once())->method('delete')->with($staticSite);
		$this->archiver->expects($this->once())->method('deleteAll')->with($this->staticSiteId);

		$this->remover->remove($staticSite);
	}

	public function testRemoveAsksGeneratorToDeletePublishedStaticSite(): void {
		$staticSite = $this->makeStaticSite(published: true);
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

		$this->remover->remove($staticSite);
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

		$this->remover->remove($staticSite);
	}

	public function testRemoveRejectsStaticSiteChangedMeanwhile(): void {
		$this->staticSiteMapper->method('startDeletion')->willReturn(false);
		$this->generatorClient->expects($this->never())->method('delete');

		$this->expectException(UnprocessableEntityException::class);
		$this->remover->remove($this->makeStaticSite(published: true));
	}

	public function testRemoveRequiresConfiguredGeneratorForPublishedStaticSite(): void {
		$this->generatorClient->method('assertConfigured')
			->willThrowException(new StaticSiteGeneratorNotConfiguredException('Not configured'));
		$this->staticSiteMapper->expects($this->never())->method('startDeletion');

		$this->expectException(StaticSiteGeneratorNotConfiguredException::class);
		$this->remover->remove($this->makeStaticSite(published: true));
	}

	public function testRemoveRestoresStaticSiteIfGeneratorRejectsRequest(): void {
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->method('delete')->willThrowException(new StaticSiteGeneratorException('Rejected'));
		$this->staticSiteMapper->expects($this->once())
			->method('restoreStatus')
			->with($this->callback(fn (StaticSite $previous): bool => $previous->getStatus() === StaticSite::STATUS_PUBLISHED
				&& $previous->getBuildId() === $this->buildId));
		$this->archiver->expects($this->never())->method('deleteAll');

		$this->expectException(StaticSiteGeneratorException::class);
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
	public function testRemoveKeepsStaticSiteDeletingIfGeneratorIsUnavailable(): void {
		$this->staticSiteMapper->method('startDeletion')->willReturn(true);
		$this->generatorClient->method('delete')->willThrowException(new StaticSiteGeneratorUnavailableException('Unavailable'));
		$this->staticSiteMapper->expects($this->never())->method('restoreStatus');
		$this->archiver->expects($this->never())->method('deleteAll');

		$this->expectException(StaticSiteGeneratorUnavailableException::class);
		$this->remover->remove($this->makeStaticSite(published: true));
	}

	public function testRemoveDeletedDeletesStaticSiteAndArchives(): void {
		$staticSite = $this->makeStaticSite(published: true);
		$this->staticSiteMapper->expects($this->once())->method('deleteIfCurrentBuild')->with($staticSite)->willReturn(true);
		$this->archiver->expects($this->once())->method('deleteAll')->with($this->staticSiteId);

		$this->remover->removeDeleted($staticSite);
	}

	public function testRemoveDeletedKeepsStaticSiteIfAnotherRequestStarted(): void {
		$this->staticSiteMapper->method('deleteIfCurrentBuild')->willReturn(false);
		$this->archiver->expects($this->never())->method('deleteAll');
		$this->logger->expects($this->once())->method('warning');

		$this->remover->removeDeleted($this->makeStaticSite(published: true));
	}

	public function testRemoveSucceedsIfArchivesCannotBeDeleted(): void {
		$this->archiver->method('deleteAll')->willThrowException(new ServiceException('Failed'));
		$this->staticSiteMapper->expects($this->once())->method('delete');
		$this->logger->expects($this->once())->method('warning');

		$this->remover->remove($this->makeStaticSite(published: false));
	}

	public function testRemoveAllOfCollectiveRemovesEachStaticSite(): void {
		$neverPublished = $this->makeStaticSite(published: false);
		$published = $this->makeStaticSite(published: true);
		$published->setId(8);
		$this->staticSiteMapper->method('findByCollectiveId')->with(42)->willReturn([$neverPublished, $published]);
		$this->staticSiteMapper->expects($this->once())->method('delete')->with($neverPublished);
		$this->staticSiteMapper->expects($this->once())->method('startDeletion')->with($published)->willReturn(true);
		$this->generatorClient->expects($this->once())->method('delete')->willReturn(true);

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
		$this->generatorClient->method('assertConfigured')
			->willThrowException(new StaticSiteGeneratorNotConfiguredException('Not configured'));
		$this->staticSiteMapper->expects($this->once())->method('delete')->with($second);
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
}
