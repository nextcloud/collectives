<?php

namespace Unit\Model;

use OCA\Wiki\Model\Page;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase {
	public function testFromFile(): void {
		$fileId = 1;
		$fileTitle = 'name';
		$fileMTime = '';
		$fileSize = 100;
		$fileName = 'name.md';
		$filePath = '/path/to/file/name.txt';

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getId')->willReturn($fileId);
		$file->method('getMTime')->willReturn($fileMTime);
		$file->method('getSize')->willReturn($fileSize);
		$file->method('getName')->willReturn($fileName);
		$file->method('getPath')->willReturn($filePath);

		$page = new Page();
		$page->fromFile($file);

		self::assertEquals($page->getId(), $fileId);
		self::assertEquals($page->getTitle(), $fileTitle);
		self::assertEquals($page->getTimestamp(), $fileMTime);
		self::assertEquals($page->getSize(), $fileSize);
		self::assertEquals($page->getFileName(), $fileName);
		self::assertEquals($page->getFilePath(), $filePath);
	}
}
