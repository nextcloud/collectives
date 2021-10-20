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
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase {
	private $pageMapper;
	private $nodeHelper;
	private $collectiveFolder;
	private $config;
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

		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();

		$this->service = new PageService($this->pageMapper, $this->nodeHelper, $collectiveMapper, $userFolderHelper, $this->config);

		$this->collective = new Collective();
		$this->collective->setCircleId('circleId');
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

	public function testHasSubPages(): void {
		$childFile1 = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$childFile1->method('getName')
			->willReturn('Readme.md');

		$childFile2 = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$childFile2->method('getName')
			->willReturn('File2.md');

		$childFile3 = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$childFile3->method('getName')
			->willReturn('File3.txt');

		$children = [$childFile1, $childFile2, $childFile3];
		$parentFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getParent')
			->willReturn($parentFolder);

		// Test `pageHasOtherContent()` with page in children
		$parentFolder->method('getDirectoryListing')
			->willReturnOnConsecutiveCalls(
				$children,
				[],
				$children,
				[$childFile1],
				$children,
				$children,
				$children
			);
		self::assertTrue($this->service->pageHasOtherContent($file));
		// Test `pageHasOtherContent()` without any children
		self::assertFalse($this->service->pageHasOtherContent($file));

		$subfolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$subfolder->method('getDirectoryListing')
			->willReturn([$parentFolder]);
		$subfolder->method('getName')
			->willReturn('subfolder');
		$subfolder->method('nodeExists')
			->with('Readme.md')
			->willReturn(true);
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('getDirectoryListing')
			->willReturn([$subfolder]);

		// Test `folderHasSubPages()` with page in grandchildren
		self::assertTrue(PageService::folderHasSubPages($folder));
		// Test `folderHasSubPages()` without page in grandchildren
		self::assertFalse(PageService::folderHasSubPages($folder));

		// Test `folderHasSubPage()`
		self::assertEquals(0, PageService::folderHasSubPage($parentFolder, 'File3'));
		self::assertEquals(1, PageService::folderHasSubPage($parentFolder, 'File2'));
		self::assertEquals(2, PageService::folderHasSubPage($folder, 'subfolder'));
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

	public function testGetPageLink(): void {
		$collectiveName = 'My Collective';
		$pageFile1 = new PageFile();
		$pageFile1->setId(123);
		$pageFile1->setFilePath('page one');
		$pageFile1->setFileName('subpage2.md');
		$pageFile1->setTitle('subpage2');

		self::assertEquals('My%20Collective/page%20one/subpage2?fileId=123',
			$this->service->getPageLink($collectiveName, $pageFile1));

		$pageFile2 = new PageFile();
		$pageFile2->setId(124);
		$pageFile2->setFilePath('page two/with another layer/and#spec!al_ch@rs?;');
		$pageFile2->setFileName('Readme.md');
		$pageFile2->setTitle('page');

		self::assertEquals('My%20Collective/page%20two/with%20another%20layer/and%23spec%21al_ch%40rs%3F%3B/page?fileId=124',
			$this->service->getPageLink($collectiveName, $pageFile2));
	}

	public function testMatchBacklinks(): void {
		$this->config->method('getSystemValue')
			->willReturn(['nextcloud.local']);

		$pageFile = new PageFile();
		$pageFile->setId(123);
		$pageFile->setCollectivePath('Collectives/mycollective');
		$pageFile->setFilePath('page1/pageX');
		$pageFile->setFileName('subpage2.md');
		$pageFile->setTitle('subpage2');

		$urlPathBase = '/apps/collectives/mycollective';
		$urlPath = $urlPathBase . '/page1/pageX/subpage2';

		// Relative link with fileId
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link](' . $urlPath . '?fileId=123).'));
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link](/index.php' . $urlPath . '?fileId=123).'));
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link to wrong path but correct fileId](' . $urlPathBase . '/subpage2?fileId=123).'));
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a relative link](pageX/subpage2?fileId=123).'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link to wrong fileId](' . $urlPath . '?fileId=345).'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a broken link(' . $urlPath . '?fileId=123).'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a broken link] (' . $urlPath . '?fileId=123).'));

		// Relative link without fileId
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link](' . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link to wrong path](' . $urlPathBase . '/page1/subpage2) in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link to wrong webroot](/index.php/instance2' . $urlPath . ') in it.'));

		// Absolute link with fileId
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link](http://nextcloud.local' . $urlPath . '?fileId=123) in it.'));
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link](https://nextcloud.local' . $urlPath . '?fileId=123) in it.'));
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link](https://nextcloud.local/index.php' . $urlPath . '?fileId=123) in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link to wrong host (with fileId)](https://example.org/' . $urlPath . 'fileId=123) in it.'));

		// Absolute link without fileId
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link](http://nextcloud.local' . $urlPath . ') in it.'));
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link](https://nextcloud.local' . $urlPath . ') in it.'));
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link with many slashes](https://nextcloud.local/////' . str_replace('/', '//', $urlPath) . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a broken link](https://nextcloud.local' . $urlPath . ' in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link to wrong instance](https://nextcloud.local/instance2' . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link to wrong host](https://anothercloud.com' . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link to wrong host](anothercloud.com' . $urlPath . ') in it.'));

		\OC::$WEBROOT = 'mycloud';

		// Relative link with fileId with webroot
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link with webroot](' . \OC::$WEBROOT . $urlPath . '?fileId=123).'));
		// Relative link without fileId with webroot
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link with webroot](' . \OC::$WEBROOT . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link with missing webroot](' . $urlPath . ') in it.'));
		// Absolute link with fileId with webroot
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link with webroot](http://nextcloud.local' . \OC::$WEBROOT . $urlPath . '?fileId=123) in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link with missing webroot](http://nextcloud.local' . $urlPath . '?fileId=123) in it.'));
		// Absolute link without fileId with webroot
		self::assertTrue($this->service->matchBacklinks($pageFile, 'content with [a link with webroot](http://nextcloud.local' . \OC::$WEBROOT . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageFile, 'content with [a link with missing webroot](http://nextcloud.local' . $urlPath . ') in it.'));
	}
}
