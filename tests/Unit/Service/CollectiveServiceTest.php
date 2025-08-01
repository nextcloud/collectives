<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
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
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\App\IAppManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

class CollectiveServiceTest extends TestCase {
	private string $userId = 'jane';
	private CollectiveMapper $collectiveMapper;
	private CollectiveHelper $collectiveHelper;
	private CircleHelper $circleHelper;
	private IL10N $l10n;
	private CollectiveService $service;

	protected function setUp(): void {
		$appManager = $this->createMock(IAppManager::class);

		$this->collectiveMapper = $this->createMock(CollectiveMapper::class);
		$this->collectiveHelper = $this->createMock(CollectiveHelper::class);
		$collectiveFolderManager = $this->createMock(CollectiveFolderManager::class);

		$folder = $this->createMock(Folder::class);
		$file = $this->createMock(File::class);
		$folder->method('get')
			->willReturn($file);
		$collectiveFolderManager->method('initializeFolder')
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
			->willReturnCallback(function (string $name, string $default = 'New File') {
				return $name;
			});

		$slug = new UnicodeString('free-123');
		$slugger = $this->createMock(SluggerInterface::class);
		$slugger->method('slug')->willReturn($slug);

		$this->service = new CollectiveService(
			$appManager,
			$this->collectiveMapper,
			$this->collectiveHelper,
			$collectiveFolderManager,
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
		$collective = new Collective($collective);
		$collective->setName($name);
		$this->assertEquals($name, $this->service->getCollectiveNameWithEmoji($collective));

		$collective->setEmoji($emoji);
		$this->assertEquals($emoji . ' ' . $name, $this->service->getCollectiveNameWithEmoji($collective));
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
			'canLeave' => true,
		], $collective->jsonSerialize());
	}
}
