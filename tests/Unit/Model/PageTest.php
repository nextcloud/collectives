<?php

namespace Unit\Model;

use OC\Files\Mount\MountPoint;
use OCA\Unite\Model\Page;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase {
	public function testFromFile(): void {
		$fileId = 1;
		$fileTitle = 'name';
		$fileMTime = '';
		$fileSize = 100;
		$fileName = 'name.md';
		$fileMountPoint = '/mountpoint/';
		$fileInternalPath = 'path/to/file/name.txt';

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

		$page = new Page();
		$page->fromFile($file);

		self::assertEquals($page->getId(), $fileId);
		self::assertEquals($page->getTitle(), $fileTitle);
		self::assertEquals($page->getTimestamp(), $fileMTime);
		self::assertEquals($page->getSize(), $fileSize);
		self::assertEquals($page->getFileName(), $fileName);
		self::assertEquals($page->getFilePath(), $fileMountPoint . $fileInternalPath);
	}
}
