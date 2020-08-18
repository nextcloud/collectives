<?php

namespace Unit\Model;

use OC\Files\Node\Folder;
use OCA\Unite\Db\Collective;
use OCA\Unite\Model\CollectiveInfo;
use PHPUnit\Framework\TestCase;

class CollectiveInfoTest extends TestCase {
	public function testFromCollective(): void {
		$id = 101;
		$circleUniqueId = 'circleUniqueId';
		$folderId = 123;
		$ownerId = 456;
		$name = 'testcollective';
		$folderName = 'folder';
		$folderPath = '/path/to/folder';
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('getName')
			->willReturn($folderName);
		$folder->method('getPath')
			->willReturn($folderPath);

		$collective = new Collective();
		$collective->setId($id);
		$collective->setCircleUniqueId($circleUniqueId);
		$collective->setFolderId($folderId);
		$collective->setOwnerId($ownerId);

		$wi = new CollectiveInfo();
		$wi->fromCollective($collective, $name, $folder);

		self::assertEquals($wi->getId(), $id);
		self::assertEquals($wi->getCircleUniqueId(), $circleUniqueId);
		self::assertEquals($wi->getFolderId(), $folderId);
		self::assertEquals($wi->getOwnerId(), $ownerId);
		self::assertEquals($wi->getName(), $name);
		self::assertEquals($wi->getFolderName(), $folderName);
		self::assertEquals($wi->getFolderPath(), $folderPath);
	}
}
