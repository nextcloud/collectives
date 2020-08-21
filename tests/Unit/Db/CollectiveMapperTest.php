<?php

namespace Unit\Db;

use OC\Files\Node\Folder;
use OC\Files\View;
use OCA\Unite\Db\Collective;
use OCA\Unite\Db\CollectiveMapper;
use OCA\Unite\Service\NotFoundException;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class CollectiveMapperTest extends TestCase {
	private $mapper;
	private $folder;
	private $collective1;
	private $collective2;
	private $collective3;
	private $collective4;
	private $userId = 'jane';

	protected function setUp() {
		parent::setUp();

		$root = $this->getMockBuilder(IRootFolder::class)
			->disableOriginalConstructor()
			->getMock();
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()
			->getMock();
		$this->folder = new Folder($root, $view, '/path/to/Folder');
		$userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolder->method('get')
			->willReturnMap([
				['collective1', $this->folder],
				['collective2', null],
				['collective3', null],
			]);
		$root->method('getUserFolder')
			->willReturn($userFolder);

		$db = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();

		$this->collective1 = new Collective();
		$this->collective1->setId(1);
		$this->collective1->setName('collective1');
		$this->collective2 = new Collective();
		$this->collective2->setId(2);
		$this->collective2->setName('collective2');
		$this->collective3 = new Collective();
		$this->collective3->setId(3);
		$this->collective3->setName('collective3');

		$this->mapper = $this->getMockBuilder(CollectiveMapper::class)
			->setConstructorArgs([$root, $db])
			->setMethods(['findById'])
			->getMock();
		$this->mapper->method('findById')
			->willReturnMap([
				[1, $this->collective1],
				[2, $this->collective2],
				[3, null]
			]);
	}

	public function testGetCollectiveFolder(): void {
		self::assertEquals($this->folder, $this->mapper->getCollectiveFolder($this->collective1, $this->userId));
	}

	public function testGetCollectiveFolderNotFoundException(): void {
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage("Folder not found for collective collective2");
		$this->mapper->getCollectiveFolder($this->collective2, $this->userId);
	}

	public function testUserHasCollectiveCollectiveNull(): void {
		$this->assertNull($this->mapper->userHasCollective($this->collective1, $this->userId));
	}
}
