<?php

namespace Unit\Db;

use OC\Files\Node\Folder;
use OC\Files\View;
use OCA\Wiki\Db\Wiki;
use OCA\Wiki\Db\WikiMapper;
use OCA\Wiki\Service\NotFoundException;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class WikiMapperTest extends TestCase {
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

		$wiki1 = new Wiki();
		$wiki1->setFolderId(1);
		$wiki2 = new Wiki();
		$wiki2->setFolderId(2);
		$wiki4 = new Wiki();
		$wiki4->setFolderId(4);

		$this->mapper = $this->getMockBuilder(WikiMapper::class)
			->setConstructorArgs([$root, $db])
			->setMethods(['findById'])
			->getMock();
		$this->mapper->method('findById')
			->willReturnMap([
				[1, $wiki1],
				[2, $wiki2],
				[3, null],
				[4, $wiki4]
			]);
	}

	public function testFindById(): void {
		self::assertEquals($this->folder, $this->mapper->getWikiFolder(1));
		self::assertEquals($this->folder, $this->mapper->getWikiFolder(2));
	}

	public function testFindByIdWikiNotFoundException(): void {
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage("Wiki 3 not found");
		self::assertEquals($this->folder, $this->mapper->getWikiFolder(3));
	}

	public function testFindByIdFolderNotFoundException(): void {
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage("Wiki folder (FileID 4) not found");
		self::assertEquals($this->folder, $this->mapper->getWikiFolder(4));
	}
}
