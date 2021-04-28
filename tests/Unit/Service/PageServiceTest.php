<?php

namespace Unit\Service;

use OC\Files\Mount\MountPoint;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageFile;
use OCA\Collectives\Service\PageService;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase {
	private $pageMapper;
	private $nodeHelper;
	private $collectiveFolder;
	private $service;
	private $userId = 'jane';
	private $collective;

	protected function setUp(): void {
		$this->pageMapper = $this->getMockBuilder(PageMapper::class)
			->disableOriginalConstructor()
			->getMock();
		$this->pageMapper->method('findByFileId')
			->willReturn(null);

		$this->nodeHelper = $this->getMockBuilder(NodeHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$collectiveMapper = $this->getMockBuilder(CollectiveMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->collectiveFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();

		$userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolder->method('get')
			->willReturn($this->collectiveFolder);
		$userFolderHelper = $this->getMockBuilder(UserFolderHelper::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolderHelper->method('get')
			->willReturn($userFolder);

		$this->service = new PageService($this->pageMapper, $this->nodeHelper, $collectiveMapper, $userFolderHelper);

		$this->collective = new Collective();
		$this->collective->setCircleUniqueId('circleUniqueId');
	}

	public function testGetFolder(): void {
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getParent')
			->willReturn($folder);
		$this->nodeHelper->method('getFileById')
			->willReturn($file);
		self::assertEquals($this->collectiveFolder, $this->service->getFolder($this->userId, $this->collective, 0));
		self::assertEquals($folder, $this->service->getFolder($this->userId, $this->collective, 1));
	}

	public function testInitSubFolder(): void {
		$subFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('newFolder')
			->willReturn($subFolder);
		$indexFile = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$indexFile->method('getParent')
			->willReturn($folder);
		$indexFile->method('getName')
			->willReturn(PageFile::INDEX_PAGE_TITLE . PageFile::SUFFIX);
		$otherFile = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$otherFile->method('getParent')
			->willReturn($folder);
		$otherFile->method('getName')
			->willReturn('something.md');

		self::assertEquals($folder, $this->service->initSubFolder($indexFile));
		self::assertEquals($subFolder, $this->service->initSubFolder($otherFile));
	}

	public function testIsPage(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getName')
			->willReturnOnConsecutiveCalls(
				'page.md', 'image.gz'
			);

		self::assertTrue(PageService::isPage($file));
		self::assertFalse(PageService::isPage($file));
	}

	public function testIsIndexPage(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getName')
			->willReturnOnConsecutiveCalls(
				'Readme.md', 'page.md'
			);
		self::assertTrue(PageService::isIndexPage($file));
		self::assertFalse(PageService::isIndexPage($file));
	}

	public function testRecurseFolder(): void {
		$filesNotJustMd = [];
		$filesJustMd = [];
		$pageFiles = [];

		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('getParent')
			->willReturn($folder);
		$folder->method('getName')
			->willReturn('testfolder');

		$mountPoint = $this->getMockBuilder(MountPoint::class)
			->disableOriginalConstructor()
			->getMock();
		$mountPoint->method('getMountPoint')->willReturn('/files/user/Collectives/collective/');

		$indexFile = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$indexFile->method('getId')
			->willReturn('101');
		$indexFile->method('getName')
			->willReturn('Readme.md');
		$folder->method('get')
			->willReturn($indexFile);
		$indexFile->method('getParent')
			->willReturn($folder);
		$indexFile->method('getMountPoint')
			->willReturn($mountPoint);
		$indexPage = new Page();
		$this->pageMapper->method('findByFileId')
			->willReturn($indexPage);
		$indexPageFile = new PageFile();
		$indexPageFile->fromFile($indexFile, 1);
		$indexPageFile->setParentId(101);
		$indexPageFile->setTitle('testfolder');

		$filesJustMd[] = $indexPageFile;
		$filesNotJustMd[] = $indexPageFile;
		$pageFiles[] = $indexPageFile;

		$fileNameList = [ 'page1.md', 'page2.md', 'page3.md', 'another.jpg', 'whatever.txt' ];
		foreach ($fileNameList as $fileName) {

			// Add all files to $filesNotJustMd
			$file = $this->getMockBuilder(File::class)
				->disableOriginalConstructor()
				->getMock();
			$file->method('getId')
				->willReturn(1);
			$file->method('getName')
				->willReturn($fileName);
			$file->method('getParent')
				->willReturn($folder);
			$file->method('getMountPoint')
				->willReturn($mountPoint);
			$filesNotJustMd[] = $file;

			// Only add markdown files to $filesJustMd
			if (!PageService::isPage($file)) {
				continue;
			}

			$filesJustMd[] = $file;

			$pageFile = new PageFile();
			$pageFile->fromFile($file, 1);
			$pageFile->setParentId(101);
			$pageFiles[] = $pageFile;
		}

		$folder->method('getDirectoryListing')
			->willReturnOnConsecutiveCalls(
				$filesJustMd,
				$filesNotJustMd,
			);

		self::assertEquals($pageFiles, $this->service->recurseFolder($this->userId, $folder));
		self::assertEquals($pageFiles, $this->service->recurseFolder($this->userId, $folder));
	}
}
