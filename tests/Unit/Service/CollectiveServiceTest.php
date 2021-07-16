<?php

namespace Unit\Service;

use OCA\Circles\Model\Circle;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\CircleExistsException;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class CollectiveServiceTest extends TestCase {
	private $service;
	private $userId = 'jane';
	private $collectiveMapper;
	private $circleHelper;
	private $l10n;

	protected function setUp(): void {
		$this->collectiveMapper = $this->getMockBuilder(CollectiveMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$collectiveHelper = $this->getMockBuilder(CollectiveHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$collectiveFolderManager = $this->getMockBuilder(CollectiveFolderManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->circleHelper = $this->getMockBuilder(CircleHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();

		$this->service = new CollectiveService(
			$this->collectiveMapper,
			$collectiveHelper,
			$collectiveFolderManager,
			$this->circleHelper,
			$this->l10n
		);
	}

	public function testCreateWithEmptyName(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('Empty collective name is not allowed');
		$this->service->createCollective($this->userId, 'de', '');
	}

	public function testCreateWithExistingCircle(): void {
		$this->circleHelper->method('createCircle')
			->willThrowException(new CircleExistsException('A circle with that name exists'));
		$this->circleHelper->method('findCircle')
			->willReturn(null);
		$this->expectException(CircleExistsException::class);
		$this->expectExceptionMessage('A circle with that name exists');
		$this->service->createCollective($this->userId, 'de', 'taken');
	}

	public function testCreateForOwnCircle(): void {
		$circle = $this->getMockBuilder(Circle::class)
			->disableOriginalConstructor()
			->getMock();
		$circle->method('getUniqueId')
			->willReturn('CircleId');
		$circle->method('getName')
			->willReturn('own');
		$collective = new Collective();
		$collective->setId(123);
		$this->circleHelper->method('createCircle')
			->willThrowException(new CircleExistsException('A circle with that name exists'));
		$this->circleHelper->method('findCircle')
			->willReturn($circle);
		$this->circleHelper->method('isAdmin')
			->willReturn(true);
		$this->collectiveMapper->method('findByCircleId')
			->willReturn(null);
		$this->collectiveMapper
			->expects(self::once())
			->method('insert')
			->with(self::callback(function ($collective) {
				return is_callable([$collective, 'getCircleId']) &&
					$collective->getCircleId() === 'CircleId';
			}))
			->willReturn($collective);
		$this->l10n
			->expects(self::once())
			->method('t')
			->willReturn('Created collective "own" for existing circle.');
		[$collective, $info] = $this->service->createCollective($this->userId, 'de', 'own');
		self::assertIsCallable([$collective, 'jsonSerialize']);
		self::assertEquals('Created collective "own" for existing circle.', $info);
	}

	public function testCreate(): void {
		$circle = $this->getMockBuilder(Circle::class)
			->disableOriginalConstructor()
			->getMock();
		$circle->method('getUniqueId')
			->willReturn('CircleId');
		$circle->method('getSanitizedName')
			->willReturn('free');
		$collective = new Collective();
		$collective->setId(123);
		$this->circleHelper->method('createCircle')
			->willReturn($circle);
		$this->collectiveMapper
			->expects(self::once())
			->method('insert')
			->with(self::callback(function ($collective) {
				return is_callable([$collective, 'getCircleId']) &&
					$collective->getCircleId() === 'CircleId';
			}))
			->willReturn($collective);
		[$collective, $info] = $this->service->createCollective($this->userId, 'de', 'free');
		self::assertIsCallable([$collective, 'jsonSerialize']);
		self::assertEqualsCanonicalizing([
			'id' => 123,
			'emoji' => null,
			'circleId' => null,
			'trashTimestamp' => null,
			'name' => 'free',
			'admin' => true
		], $collective->jsonSerialize());
	}
}
