<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Db\TagMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\CircleExistsException;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\App\IAppManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IL10N;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;
use Test\TestCase;

class CollectiveServiceTest extends TestCase {
	private string $userId = 'jane';
	private CollectiveMapper $collectiveMapper;
	private CollectiveHelper $collectiveHelper;
	private CollectiveFolderManager $collectiveFolderManager;
	private CircleHelper $circleHelper;
	private IL10N $l10n;
	private CollectiveService $service;

	protected function setUp(): void {
		parent::setUp();
		$appManager = $this->createMock(IAppManager::class);

		$this->collectiveMapper = $this->createMock(CollectiveMapper::class);
		$this->collectiveHelper = $this->createMock(CollectiveHelper::class);
		$this->collectiveFolderManager = $this->createMock(CollectiveFolderManager::class);

		$folder = $this->createMock(Folder::class);
		$file = $this->createMock(File::class);
		$folder->method('get')
			->willReturn($file);
		$this->collectiveFolderManager->method('initializeFolder')
			->willReturn($folder);

		$this->circleHelper = $this->createMock(CircleHelper::class);
		$shareService = $this->createMock(CollectiveShareService::class);
		$collectiveUserSettingsMapper = $this->createMock(CollectiveUserSettingsMapper::class);
		$pageMapper = $this->createMock(PageMapper::class);
		$tagMapper = $this->createMock(TagMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$eventDispatcher = $this->createMock(IEventDispatcher::class);

		$nodeHelper = $this->createMock(NodeHelper::class);
		$nodeHelper->method('sanitiseFilename')
			->willReturnCallback(fn (string $name, string $default = 'New File') => $name);

		$slug = new UnicodeString('free-123');
		$slugger = $this->createMock(SluggerInterface::class);
		$slugger->method('slug')->willReturn($slug);

		$this->service = new CollectiveService(
			$appManager,
			$this->collectiveMapper,
			$this->collectiveHelper,
			$this->collectiveFolderManager,
			$this->circleHelper,
			$shareService,
			$collectiveUserSettingsMapper,
			$pageMapper,
			$tagMapper,
			$this->l10n,
			$eventDispatcher,
			$nodeHelper,
			$slugger,
		);
	}

	public function testFindCollectiveByName(): void {
		$collective1 = new Collective();
		$collective2 = new Collective();
		$collective1 = new Collective($collective1);
		$collective1->setName('collective1');
		$collective2 = new Collective($collective2);
		$collective2->setName('collective2');
		$this->collectiveHelper->method('getCollectivesForUser')
			->willReturn([$collective1, $collective2]);

		$this->assertEquals($collective1, $this->service->findCollectiveByName($this->userId, 'collective1'));

		$this->expectException(NotFoundException::class);
		$this->service->findCollectiveByName($this->userId, 'collective3');
	}

	public function testGetCollectiveNameWithEmoji(): void {
		$name = 'collective';
		$emoji = '⭐';
		$collective = new Collective();
		$collective->setName($name);
		$this->assertEquals($name, CollectiveHelper::getCollectiveNameWithEmoji($collective));

		$collective->setEmoji($emoji);
		$this->assertEquals($emoji . ' ' . $name, CollectiveHelper::getCollectiveNameWithEmoji($collective));
	}

	public function testCreateWithEmptyName(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('Empty collective name is not allowed');
		$this->service->createCollective($this->userId, 'de', '');
	}

	public function testCreateWithExistingCircle(): void {
		$this->circleHelper->method('createCircle')
			->willThrowException(new CircleExistsException('A team with that name exists'));
		$this->circleHelper->method('findCircle')
			->willReturn(null);
		$this->expectException(CircleExistsException::class);
		$this->expectExceptionMessage('A team with that name exists');
		$this->service->createCollective($this->userId, 'de', 'taken');
	}

	public function testCreateForOwnCircle(): void {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')
			->willReturn('CircleId');
		$circle->method('getName')
			->willReturn('own');
		$collective = new Collective();
		$collective->setId(123);
		$this->circleHelper->method('createCircle')
			->willThrowException(new CircleExistsException('A team with that name exists'));
		$this->circleHelper->method('findCircle')
			->willReturn($circle);
		$this->collectiveMapper->method('findByCircleId')
			->willReturn(null);
		$this->collectiveMapper
			->expects(self::once())
			->method('insert')
			->with(self::callback(fn ($collective) => is_callable([$collective, 'getCircleId'])
					&& $collective->getCircleId() === 'CircleId'))
			->willReturn($collective);
		$this->l10n
			->expects(self::once())
			->method('t')
			->willReturn('Created collective "own" for existing team.');
		[$collective, $info] = $this->service->createCollective($this->userId, 'de', 'own');
		self::assertIsCallable([$collective, 'jsonSerialize']);
		self::assertEquals('Created collective "own" for existing team.', $info);
	}

	public function testCreate(): void {
		$circle = $this->createMock(Circle::class);
		$circle->method('getSingleId')
			->willReturn('CircleId');
		$circle->method('getSanitizedName')
			->willReturn('free');
		$collective = new Collective();
		$collective->setId(123);
		$collective->setPermissions(Collective::defaultPermissions);
		$collective->setCanLeave(true);
		$this->circleHelper->method('createCircle')
			->willReturn($circle);
		$this->circleHelper->method('getLevel')
			->willReturn(Member::LEVEL_OWNER);
		$this->collectiveMapper
			->expects(self::once())
			->method('insert')
			->with(self::callback(fn ($collective) => is_callable([$collective, 'getCircleId'])
					&& $collective->getCircleId() === 'CircleId'))
			->willReturn($collective);
		[$collective, $info] = $this->service->createCollective($this->userId, 'de', 'free');
		self::assertIsCallable([$collective, 'jsonSerialize']);
		self::assertEquals([
			'id' => 123,
			'slug' => 'free-123',
			'circleId' => null,
			'emoji' => null,
			'trashTimestamp' => null,
			'pageMode' => 0,
			'name' => 'free',
			'level' => Member::LEVEL_OWNER,
			'editPermissionLevel' => 1,
			'sharePermissionLevel' => 1,
			'canEdit' => true,
			'canShare' => true,
			'shareToken' => null,
			'isPageShare' => false,
			'sharePageId' => 0,
			'shareEditable' => false,
			'userPageOrder' => 0,
			'userShowMembers' => true,
			'userShowRecentPages' => true,
			'userFavoritePages' => [],
			'userNotify' => 1,
			'canLeave' => true,
		], $collective->jsonSerialize());
	}

	private function mockTrashedCollective(): Collective {
		$collective = new Collective();
		$collective->setId(123);
		$collective->setCircleId('CircleId');
		$collective->setTrashTimestamp(1700000000);
		$this->collectiveMapper->method('findTrashByIdAndUser')
			->with(123, $this->userId)
			->willReturn($collective);
		return $collective;
	}

	public function testDeleteCollectiveDestroysCircleAfterPurge(): void {
		$collective = $this->mockTrashedCollective();
		$this->circleHelper->method('isOwner')
			->willReturn(true);
		$this->collectiveFolderManager->method('getFolder')
			->willThrowException(new FilesNotFoundException());

		$calls = [];
		$this->collectiveMapper->expects(self::once())
			->method('delete')
			->with($collective)
			->willReturnCallback(function (Collective $collective) use (&$calls): Collective {
				$calls[] = 'purge';
				return $collective;
			});
		$this->circleHelper->expects(self::once())
			->method('destroyCircle')
			->with('CircleId', $this->userId)
			->willReturnCallback(function () use (&$calls): void {
				$calls[] = 'destroyCircle';
			});

		$this->service->deleteCollective(123, $this->userId, true);
		self::assertSame(['purge', 'destroyCircle'], $calls);
	}

	public function testDeleteCollectiveKeepCircleUnflagsCircleAfterPurge(): void {
		$collective = $this->mockTrashedCollective();
		$this->collectiveFolderManager->method('getFolder')
			->willThrowException(new FilesNotFoundException());

		$calls = [];
		$this->collectiveMapper->expects(self::once())
			->method('delete')
			->with($collective)
			->willReturnCallback(function (Collective $collective) use (&$calls): Collective {
				$calls[] = 'purge';
				return $collective;
			});
		$this->circleHelper->expects(self::never())
			->method('destroyCircle');
		$this->circleHelper->expects(self::once())
			->method('unflagCircleAsAppManaged')
			->with('CircleId')
			->willReturnCallback(function () use (&$calls): void {
				$calls[] = 'unflagCircle';
			});

		$this->service->deleteCollective(123, $this->userId, false);
		self::assertSame(['purge', 'unflagCircle'], $calls);
	}

	public function testDeleteCollectiveKeepsCircleIfPurgeFails(): void {
		$this->mockTrashedCollective();
		$this->circleHelper->method('isOwner')
			->willReturn(true);
		$this->collectiveFolderManager->method('getFolder')
			->willThrowException(new InvalidPathException());

		$this->collectiveMapper->expects(self::never())
			->method('delete');
		$this->circleHelper->expects(self::never())
			->method('destroyCircle');
		$this->circleHelper->expects(self::never())
			->method('unflagCircleAsAppManaged');

		$this->expectException(NotFoundException::class);
		$this->service->deleteCollective(123, $this->userId, true);
	}

	public function testDeleteCollectiveAndCircleAsNonOwnerFailsBeforePurge(): void {
		$this->mockTrashedCollective();
		$this->circleHelper->method('isOwner')
			->willReturn(false);

		$this->collectiveFolderManager->expects(self::never())
			->method('getFolder');
		$this->collectiveMapper->expects(self::never())
			->method('delete');
		$this->circleHelper->expects(self::never())
			->method('destroyCircle');

		$this->expectException(NotPermittedException::class);
		$this->service->deleteCollective(123, $this->userId, true);
	}
}
