<?php

namespace Unit\Db;

use OCA\Wiki\Db\Page;
use OCP\Files\File;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase {

	public function testFromFile(): void {
		$fileId = 1;
		$fileTitle = 'title';
		$fileContent = 'content';

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getId')->willReturn($fileId);
		$file->method('getName')->willReturn($fileTitle);
		$file->method('getContent')->willReturn($fileContent);

		$page = Page::fromFile($file);

		$this->assertEquals($page->getId(), $fileId);
		$this->assertEquals($page->getTitle(), $fileTitle);
		$this->assertEquals($page->getContent(), $fileContent);
	}
}
