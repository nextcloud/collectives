<?php

namespace Unit\Service;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Wiki\Db\WikiMapper;
use OCA\Wiki\Fs\NodeHelper;
use OCA\Wiki\Model\Page;
use OCA\Wiki\Service\NotFoundException;
use OCA\Wiki\Service\PageDoesNotExistException;
use OCA\Wiki\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\AlreadyExistsException;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase {
	private $wikiFolder;
	private $service;
	private $userId = 'jane';

	protected function setUp(): void {
		$nodeHelper = $this->getMockBuilder(NodeHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$wikiMapper = $this->getMockBuilder(WikiMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->service = new PageService($nodeHelper, $wikiMapper);

		$this->wikiFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$wikiMapper->method('getWikiFolder')
			->willReturn($this->wikiFolder);
	}

	public function testIsPage(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getName')
			->willReturnOnConsecutiveCalls(
				'page.md', 'image.jpg'
			);

		self::assertTrue($this->service->isPage($file));
		self::assertFalse($this->service->isPage($file));
	}

	public function testFindAll(): void {
		$fileNameList = [ 'page1.md', 'page2.md', 'page3.md', 'image.png', 'text.txt' ];
		$filesNotJustMd = [];
		$filesJustMd = [];
		$pages = [];
		foreach ($fileNameList as $fileName) {
			// Add all files to $filesNotJustMd
			$file = $this->getMockBuilder(File::class)
				->disableOriginalConstructor()
				->getMock();
			$file->method('getName')
				->willReturn($fileName);
			$filesNotJustMd[] = $file;

			// Only add markdown files to $filesJustMd
			if (!$this->service->isPage($file)) {
				continue;
			}
			$filesJustMd[] = $file;

			$pages[] = Page::fromFile($file);
		}

		$this->wikiFolder->method('getDirectoryListing')
			->willReturnOnConsecutiveCalls(
				$filesJustMd,
				$filesNotJustMd
			);

		self::assertEquals($pages, $this->service->findAll($this->userId, 1));
		self::assertEquals($pages, $this->service->findAll($this->userId, 2));
	}

	public function testHandleExceptionDoesNotExistException(): void {
		$this->expectException(NotFoundException::class);
		$this->service->handleException(new DoesNotExistException('msg'));
	}

	public function testHandleExceptionMultipleObjectsReturnedException(): void {
		$this->expectException(NotFoundException::class);
		$this->service->handleException(new MultipleObjectsReturnedException('msg'));
	}

	public function testHandleExceptionAlreadyExistsException(): void {
		$this->expectException(NotFoundException::class);
		$this->service->handleException(new AlreadyExistsException('msg'));
	}

	public function testHandleExceptionPageDoesNotExistException(): void {
		$this->expectException(NotFoundException::class);
		$this->service->handleException(new PageDoesNotExistException('msg'));
	}

	public function testHandleExceptionOtherException(): void {
		$this->expectException(\RuntimeException::class);
		$this->service->handleException(new \RuntimeException('msg'));
	}
}
