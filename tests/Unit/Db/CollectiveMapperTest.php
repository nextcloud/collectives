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

	protected function setUp() {
		parent::setUp();

		$root = $this->getMockBuilder(IRootFolder::class)
			->disableOriginalConstructor()
			->getMock();
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()
			->getMock();
		$this->folder = new Folder($root, $view, '/path/to/Folder');

		$root->method('getById')
			->willReturnMap([
				[1, [$this->folder]],
				[2, [$this->folder, $this->folder]],
				[4, []]
			]);

		$db = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();

		$collective1 = new Collective();
		$collective1->setFolderId(1);
		$collective2 = new Collective();
		$collective2->setFolderId(2);
		$collective4 = new Collective();
		$collective4->setFolderId(4);

		$this->mapper = $this->getMockBuilder(CollectiveMapper::class)
			->setConstructorArgs([$root, $db])
			->setMethods(['findById'])
			->getMock();
		$this->mapper->method('findById')
			->willReturnMap([
				[1, $collective1],
				[2, $collective2],
				[3, null],
				[4, $collective4]
			]);
	}

	public function testFindById(): void {
		self::assertEquals($this->folder, $this->mapper->getCollectiveFolder(1));
		self::assertEquals($this->folder, $this->mapper->getCollectiveFolder(2));
	}

	public function testFindByIdCollectiveNotFoundException(): void {
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage("Collective 3 not found");
		self::assertEquals($this->folder, $this->mapper->getCollectiveFolder(3));
	}

	public function testFindByIdFolderNotFoundException(): void {
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage("Collective folder (FileID 4) not found");
		self::assertEquals($this->folder, $this->mapper->getCollectiveFolder(4));
	}
}
