<?php

namespace Unit\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\QueryException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
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
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Empty collective name is not allowed');
		$this->service->createCollective($this->userId, 'de', '', '');
	}

	public function testCreateWithExistingName(): void {
		$this->collectiveMapper->method('findByName')
			->willReturn(new Collective());
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('Collective already exists: taken');
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

}
