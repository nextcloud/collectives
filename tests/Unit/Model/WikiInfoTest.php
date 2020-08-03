<?php

namespace Unit\Model;

use OC\Files\Node\Folder;
use OCA\Wiki\Db\Wiki;
use OCA\Wiki\Model\WikiInfo;
use PHPUnit\Framework\TestCase;

class WikiInfoTest extends TestCase {
	public function testFromWiki(): void {
		$id = 101;
		$circleUniqueId = 'circleUniqueId';
		$folderId = 123;
		$ownerId = 456;
		$name = 'testwiki';
		$folderName = 'folder';
		$folderPath = '/path/to/folder';
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('getName')
			->willReturn($folderName);
		$folder->method('getPath')
			->willReturn($folderPath);

		$wiki = new Wiki();
		$wiki->setId($id);
		$wiki->setCircleUniqueId($circleUniqueId);
		$wiki->setFolderId($folderId);
		$wiki->setOwnerId($ownerId);

		$wi = new WikiInfo();
		$wi->fromWiki($wiki, $name, $folder);

		self::assertEquals($wi->getId(), $id);
		self::assertEquals($wi->getCircleUniqueId(), $circleUniqueId);
		self::assertEquals($wi->getFolderId(), $folderId);
		self::assertEquals($wi->getOwnerId(), $ownerId);
		self::assertEquals($wi->getName(), $name);
		self::assertEquals($wi->getFolderName(), $folderName);
		self::assertEquals($wi->getFolderPath(), $folderPath);
	}
}
