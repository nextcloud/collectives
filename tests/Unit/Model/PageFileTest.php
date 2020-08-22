<?php

namespace Unit\Model;

use OC\Files\Mount\MountPoint;
use OCA\Collectives\Model\PageFile;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;

class PageFileTest extends TestCase {
	public function testFromFile(): void {
		$fileId = 1;
		$fileTitle = 'name';
		$fileMTime = '';
		$fileSize = 100;
		$fileName = 'name.md';
		$fileMountPoint = '/mountpoint/';
		$fileInternalPath = 'path/to/file/name.txt';
		$userId = 'jane';

		$mountPoint = $this->getMockBuilder(MountPoint::class)
			->disableOriginalConstructor()
			->getMock();
		$mountPoint->method('getMountPoint')->willReturn($fileMountPoint);

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getId')->willReturn($fileId);
		$file->method('getMTime')->willReturn($fileMTime);
		$file->method('getSize')->willReturn($fileSize);
		$file->method('getName')->willReturn($fileName);
		$file->method('getMountPoint')->willReturn($mountPoint);
		$file->method('getInternalPath')->willReturn($fileInternalPath);

		$pageFile = new PageFile();
		$pageFile->fromFile($file, $userId);

		self::assertEquals($pageFile->getId(), $fileId);
		self::assertEquals($pageFile->getTitle(), $fileTitle);
		self::assertEquals($pageFile->getTimestamp(), $fileMTime);
		self::assertEquals($pageFile->getSize(), $fileSize);
		self::assertEquals($pageFile->getFileName(), $fileName);
		self::assertEquals($pageFile->getFilePath(), $fileMountPoint . $fileInternalPath);
	}
}
