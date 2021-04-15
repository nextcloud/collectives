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

		$this->mapper = $this->getMockBuilder(CollectiveMapper::class)
			->setConstructorArgs([$db])
			->getMock();
		$this->mapper->method('findById')
			->willReturn($this->collective);
	}

	public function testIsMemberFalse(): void {
		self::assertFalse($this->mapper->isMember($this->collective, $this->userId));
	}
}
