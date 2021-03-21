<?php

namespace Unit\Db;

use OC\Files\Node\Folder;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class CollectiveMapperTest extends TestCase {
	private $mapper;
	private $collective;
	private $userId = 'jane';

	protected function setUp(): void {
		parent::setUp();

		$userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolder->method('get')
			->willReturn(null);

		$db = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();

		$userFolderHelper = $this->getMockBuilder(UserFolderHelper::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolderHelper->method('get')
			->willReturn($userFolder);

		$this->collective = new Collective();
		$this->collective->setId(1);
		$this->collective->setName('collective');

		$this->mapper = $this->getMockBuilder(CollectiveMapper::class)
			->setConstructorArgs([$db, $userFolderHelper])
			->setMethods(['findById'])
			->getMock();
		$this->mapper->method('findById')
			->willReturn($this->collective);
	}

	public function testUserHasCollectiveCollectiveNull(): void {
		self::assertNull($this->mapper->userHasCollective($this->collective, $this->userId));
	}
}
