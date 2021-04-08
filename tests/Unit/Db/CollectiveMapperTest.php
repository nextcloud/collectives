<?php

namespace Unit\Db;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class CollectiveMapperTest extends TestCase {
	private $mapper;
	private $collective;
	private $userId = 'jane';

	protected function setUp(): void {
		parent::setUp();

		$db = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();

		$this->collective = new Collective();
		$this->collective->setId(1);
		$this->collective->setName('collective');

		$this->mapper = $this->getMockBuilder(CollectiveMapper::class)
			->setConstructorArgs([$db])
			->setMethods(['findById'])
			->getMock();
		$this->mapper->method('findById')
			->willReturn($this->collective);
	}

	public function testUserHasCollectiveCollectiveNull(): void {
		self::assertNull($this->mapper->userHasCollective($this->collective, $this->userId));
	}
}
