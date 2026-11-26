<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Listeners;

use OCA\Circles\Events\MembershipsRemovedEvent;
use OCA\Circles\Model\Membership;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Listeners\MembershipsRemovedListener;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\NotPermittedException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class MembershipsRemovedListenerTest extends TestCase {
	private CollectiveShareService&MockObject $shareService;
	private LoggerInterface&MockObject $logger;
	private MembershipsRemovedListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$collective = new Collective();
		$collective->setId(1);
		$collective->setCircleId('collectiveCircle');

		$collectiveMapper = $this->createMock(CollectiveMapper::class);
		$collectiveMapper->method('findByCircleId')
			->willReturnMap([
				['collectiveCircle', true, $collective],
				['otherCircle', true, null],
			]);

		$circleHelper = $this->createMock(CircleHelper::class);
		$circleHelper->method('getLocalUserId')
			->willReturnCallback(static fn (string $singleId): ?string => match ($singleId) {
				'aliceSingleId' => 'alice',
				'bobSingleId' => 'bob',
				'groupSingleId' => null,
				default => throw new NotPermittedException('Failed to get federated user'),
			});

		$this->shareService = $this->createMock(CollectiveShareService::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new MembershipsRemovedListener(
			$collectiveMapper,
			$circleHelper,
			$this->shareService,
			$this->logger,
		);
	}

	private function membership(string $circleId, string $singleId): Membership {
		$membership = $this->createMock(Membership::class);
		$membership->method('getCircleId')->willReturn($circleId);
		$membership->method('getSingleId')->willReturn($singleId);
		return $membership;
	}

	public function testDeletesSharesOfFormerMember(): void {
		$this->shareService->expects(self::once())
			->method('deleteSharesByCollectiveIdAndUser')
			->with(1, 'alice');

		$this->listener->handle(new MembershipsRemovedEvent([$this->membership('collectiveCircle', 'aliceSingleId')]));
	}

	public function testIgnoresTeamsWithoutCollective(): void {
		$this->shareService->expects(self::never())
			->method('deleteSharesByCollectiveIdAndUser');

		$this->listener->handle(new MembershipsRemovedEvent([$this->membership('otherCircle', 'aliceSingleId')]));
	}

	public function testIgnoresMembersThatAreNoLocalUsers(): void {
		$this->shareService->expects(self::never())
			->method('deleteSharesByCollectiveIdAndUser');

		$this->listener->handle(new MembershipsRemovedEvent([$this->membership('collectiveCircle', 'groupSingleId')]));
	}

	public function testContinuesAfterFailure(): void {
		$this->logger->expects(self::once())
			->method('warning');
		$this->shareService->expects(self::once())
			->method('deleteSharesByCollectiveIdAndUser')
			->with(1, 'bob');

		$this->listener->handle(new MembershipsRemovedEvent([
			$this->membership('collectiveCircle', 'unknownSingleId'),
			$this->membership('collectiveCircle', 'bobSingleId'),
		]));
	}
}
