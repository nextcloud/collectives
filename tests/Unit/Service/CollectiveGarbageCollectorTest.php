<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Circles\Model\Circle;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\CollectiveGarbageCollector;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CollectiveGarbageCollectorTest extends TestCase {
	private CircleHelper $circleHelper;
	private CollectiveService $collectiveService;
	private IAppConfig $appConfig;
	private ITimeFactory $timeFactory;
	private CollectiveGarbageCollector $garbageCollector;
	private Collective $existing;
	private Collective $orphaned;
	private Collective $failing;

	protected function setUp(): void {
		parent::setUp();

		$this->existing = new Collective();
		$this->existing->setId(1);
		$this->existing->setCircleId('existing');
		$this->orphaned = new Collective();
		$this->orphaned->setId(2);
		$this->orphaned->setCircleId('orphaned');
		$this->failing = new Collective();
		$this->failing->setId(3);
		$this->failing->setCircleId('failing');

		$collectiveMapper = $this->createMock(CollectiveMapper::class);
		$collectiveMapper->method('getAll')
			->willReturn([$this->existing, $this->orphaned, $this->failing]);

		$this->circleHelper = $this->createMock(CircleHelper::class);
		$this->collectiveService = $this->createMock(CollectiveService::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->method('getTime')->willReturn(1_000_000_000);

		$this->garbageCollector = new CollectiveGarbageCollector(
			$collectiveMapper,
			$this->circleHelper,
			$this->collectiveService,
			$this->appConfig,
			$this->timeFactory,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function mockCircles(): void {
		$this->circleHelper->method('getCircle')
			->willReturnCallback(fn (string $circleId): Circle => match ($circleId) {
				'existing' => $this->createMock(Circle::class),
				'orphaned' => throw new NotFoundException('Circle not found'),
				'failing' => throw new NotPermittedException('Database error'),
			});
	}

	public function testMarksNewlyOrphanedCollective(): void {
		$this->mockCircles();
		$this->appConfig->method('getValueArray')->willReturn([]);
		$this->appConfig->expects($this->once())
			->method('setValueArray')
			->with('collectives', 'orphaned_collectives', ['2' => 1_000_000_000]);
		$this->collectiveService->expects($this->never())->method('purgeCollective');

		self::assertEquals(0, $this->garbageCollector->purgeOrphanedCollectives());
	}

	public function testKeepsCollectiveWithinGracePeriod(): void {
		$this->mockCircles();
		$orphanedSince = 1_000_000_000 - CollectiveGarbageCollector::GRACE_PERIOD + 1;
		$this->appConfig->method('getValueArray')->willReturn(['2' => $orphanedSince]);
		$this->appConfig->expects($this->once())
			->method('setValueArray')
			->with('collectives', 'orphaned_collectives', ['2' => $orphanedSince]);
		$this->collectiveService->expects($this->never())->method('purgeCollective');

		self::assertEquals(0, $this->garbageCollector->purgeOrphanedCollectives());
	}

	public function testPurgesCollectiveAfterGracePeriod(): void {
		$this->mockCircles();
		$this->appConfig->method('getValueArray')
			->willReturn(['2' => 1_000_000_000 - CollectiveGarbageCollector::GRACE_PERIOD]);
		$this->appConfig->expects($this->once())
			->method('setValueArray')
			->with('collectives', 'orphaned_collectives', []);
		$this->collectiveService->expects($this->once())
			->method('purgeCollective')
			->with($this->orphaned)
			->willReturn($this->orphaned);

		self::assertEquals(1, $this->garbageCollector->purgeOrphanedCollectives());
	}

	public function testClearsMarkWhenTeamReappears(): void {
		$this->mockCircles();
		// collectives 1 (existing) and 3 (failing check) were marked before
		$this->appConfig->method('getValueArray')->willReturn(['1' => 1, '3' => 1]);
		$this->appConfig->expects($this->once())
			->method('setValueArray')
			->with('collectives', 'orphaned_collectives', ['2' => 1_000_000_000]);
		$this->collectiveService->expects($this->never())->method('purgeCollective');

		self::assertEquals(0, $this->garbageCollector->purgeOrphanedCollectives());
	}

	public function testSkipsWhenAllCollectivesOrphaned(): void {
		$this->circleHelper->method('getCircle')
			->willThrowException(new NotFoundException('Circle not found'));
		$this->appConfig->method('getValueArray')->willReturn(['1' => 1, '2' => 1, '3' => 1]);
		$this->appConfig->expects($this->never())->method('setValueArray');
		$this->collectiveService->expects($this->never())->method('purgeCollective');

		self::assertEquals(0, $this->garbageCollector->purgeOrphanedCollectives());
	}

	public function testPurgeOrphanedCollectivesWithoutCirclesApp(): void {
		$this->circleHelper->method('getCircle')
			->willThrowException(new MissingDependencyException('Teams app disabled'));
		$this->collectiveService->expects($this->never())
			->method('purgeCollective');
		$this->appConfig->expects($this->never())->method('setValueArray');

		self::assertEquals(0, $this->garbageCollector->purgeOrphanedCollectives());
	}
}
