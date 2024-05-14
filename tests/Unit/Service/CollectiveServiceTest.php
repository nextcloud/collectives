<?php

namespace Unit\Service;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCA\Collectives\Db\PageMapper;
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

class CollectiveServiceTest extends TestCase {
	private string $userId = 'jane';
	private CollectiveMapper $collectiveMapper;
	private CollectiveHelper $collectiveHelper;
	private CircleHelper $circleHelper;
	private IL10N $l10n;
	private CollectiveService $service;

	protected function setUp(): void {
		$appManager = $this->getMockBuilder(IAppManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->collectiveMapper = $this->getMockBuilder(CollectiveMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->collectiveHelper = $this->getMockBuilder(CollectiveHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$collectiveFolderManager = $this->getMockBuilder(CollectiveFolderManager::class)
			->disableOriginalConstructor()
			->getMock();

		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('get')
			->willReturn($file);
		$collectiveFolderManager->method('initializeFolder')
			->willReturn($folder);

		$this->circleHelper = $this->getMockBuilder(CircleHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$shareService = $this->getMockBuilder(CollectiveShareService::class)
			->disableOriginalConstructor()
			->getMock();

		$collectiveUserSettingsMapper = $this->getMockBuilder(CollectiveUserSettingsMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$pageMapper = $this->getMockBuilder(PageMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();

		$eventDispatcher = $this->getMockBuilder(IEventDispatcher::class)
			->disableOriginalConstructor()
			->getMock();

		$this->service = new CollectiveService(
			$appManager,
			$this->collectiveMapper,
			$this->collectiveHelper,
			$collectiveFolderManager,
			$this->circleHelper,
			$shareService,
			$collectiveUserSettingsMapper,
			$pageMapper,
			$this->l10n,
			$eventDispatcher
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
		$circle = $this->getMockBuilder(Circle::class)
			->disableOriginalConstructor()
			->getMock();
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
			->with(self::callback(fn ($collective) => is_callable([$collective, 'getCircleId']) &&
					$collective->getCircleId() === 'CircleId'))
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
		$circle = $this->getMockBuilder(Circle::class)
			->disableOriginalConstructor()
			->getMock();
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
			->with(self::callback(fn ($collective) => is_callable([$collective, 'getCircleId']) &&
					$collective->getCircleId() === 'CircleId'))
			->willReturn($collective);
		[$collective, $info] = $this->service->createCollective($this->userId, 'de', 'free');
		self::assertIsCallable([$collective, 'jsonSerialize']);
		self::assertEqualsCanonicalizing([
			'id' => 123,
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
			'shareEditable' => false,
			'userPageOrder' => 0,
			'userShowRecentPages' => true,
			'canLeave' => true,
		], $collective->jsonSerialize());
	}
}
