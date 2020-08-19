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
		$collective->setName($name);
		$collective->setCircleUniqueId($circleUniqueId);
		$collective->setFolderId($folderId);
		$collective->setOwnerId($ownerId);

		$ci = new CollectiveInfo();
		$ci->fromCollective($collective, $folder);

		self::assertEquals($ci->getId(), $id);
		self::assertEquals($ci->getCircleUniqueId(), $circleUniqueId);
		self::assertEquals($ci->getFolderId(), $folderId);
		self::assertEquals($ci->getOwnerId(), $ownerId);
		self::assertEquals($ci->getName(), $name);
		self::assertEquals($ci->getFolderName(), $folderName);
		self::assertEquals($ci->getFolderPath(), $folderPath);
	}
}
