<?php

namespace Unit\Service;

use OCA\Circles\Model\Circle;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCA\Collectives\Service\ConflictException;
use PHPUnit\Framework\TestCase;

class CollectiveServiceTest extends TestCase {
	private $service;
	private $userId = 'jane';
	private $collectiveMapper;

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

		$this->service = new CollectiveService($this->collectiveMapper, $collectiveHelper, $collectiveFolderManager);
	}

	public function testCreateWithEmptyName(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('Empty collective name is not allowed');
		$this->service->createCollective($this->userId, 'de', '', '');
	}

	public function testCreateExistingAsMember(): void {
		$this->collectiveMapper->method('findByName')
			->willReturn(new Collective());
		$this->expectException(ConflictException::class);
		$this->expectExceptionMessage('Collective "taken" exists already.');
		$this->service->createCollective($this->userId, 'de', 'taken', 'taken');
	}

	public function testCreateWithExistingCircle(): void {
		$this->collectiveMapper->method('findByName')
			->willReturn(null);
		$this->collectiveMapper->method('createCircle')
			->will($this->throwException(new \RuntimeException('Failed to create Circle taken')));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Failed to create Circle taken');
		$this->service->createCollective($this->userId, 'de', 'taken', 'taken');
	}

	public function testCreate(): void {
		$circle = $this->getMockBuilder(Circle::class)
			->disableOriginalConstructor()
			->getMock();
		$circle->method('getUniqueId')
			->willReturn('CircleUniqueId');
		$collective = new Collective();
		$collective->setId(123);
		$this->collectiveMapper->method('findByName')
			->willReturn(null);
		$this->collectiveMapper->method('createCircle')
			->willReturn($circle);
		$this->collectiveMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function ($collective) {
				return is_callable([$collective, 'getName']) &&
					$collective->getName() == 'free';
			}))
			->willReturn($collective);
		$info = $this->service->createCollective($this->userId, 'de', 'free', 'free');
		self::assertTrue(is_callable([$info, 'jsonSerialize']));
		self::assertEqualsCanonicalizing([
			'id' => 123,
			'name' => null,
			'circleUniqueId' => null,
			'admin' => true
		], $info->jsonSerialize());
	}
}
