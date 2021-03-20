<?php

namespace Unit\Db;

use OC\Files\Node\Folder;
use OC\Files\View;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\NotFoundException;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class CollectiveMapperTest extends TestCase {
	private $mapper;
	private $folder;
	private $collective1;
	private $collective2;
	private $collective3;
	private $userId = 'jane';

	protected function setUp(): void {
		parent::setUp();

		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()
			->getMock();
		$this->folder = new Folder('', $view, '/path/to/Folder');
		$userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolder->method('get')
			->willReturnMap([
				['collective1', $this->folder],
				['collective2', null],
				['collective3', null],
			]);

		$db = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();

		$userFolderHelper = $this->getMockBuilder(UserFolderHelper::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolderHelper->method('get')
			->willReturn($userFolder);

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
			->setConstructorArgs([$db, $userFolderHelper])
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
		self::assertNull($this->mapper->userHasCollective($this->collective1, $this->userId));
	}
}
