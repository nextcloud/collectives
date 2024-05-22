<?php

namespace Unit\Service;

use OC;
use OC\App\AppManager;
use OC\Files\Mount\MountPoint;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CollectiveServiceBase;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SessionService;
use OCP\IConfig;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class PageServiceTest extends TestCase {
	private PageMapper $pageMapper;
	private NodeHelper $nodeHelper;
	private CollectiveServiceBase $collectiveService;
	private Folder $collectiveFolder;
	private IConfig $config;
	private PageService $service;
	private string $userId = 'jane';
	private int $collectiveId = 1;

	protected function setUp(): void {
		$appManager = $this->getMockBuilder(AppManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->pageMapper = $this->getMockBuilder(PageMapper::class)
			->disableOriginalConstructor()
			->getMock();
		$this->pageMapper->method('findByFileId')
			->willReturn(null);

		$this->nodeHelper = $this->getMockBuilder(NodeHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->collectiveService = $this->getMockBuilder(CollectiveServiceBase::class)
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

		$userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();

		$container = $this->getMockBuilder(ContainerInterface::class)
			->disableOriginalConstructor()
			->getMock();

		$sessionService = $this->getMockBuilder(SessionService::class)
			->disableOriginalConstructor()
			->getMock();

		$this->service = new PageService(
			$appManager,
			$this->pageMapper,
			$this->nodeHelper,
			$this->collectiveService,
			$userFolderHelper,
			$userManager,
			$this->config,
			$container,
			$sessionService);
	}

	public function testGetPageFile(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$this->nodeHelper->method('getFileById')
			->willReturn($file);

		self::assertEquals($file, $this->service->getPageFile($this->collectiveId, 1, $this->userId));
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
		self::assertEquals($this->collectiveFolder, $this->service->getFolder($this->collectiveId, 0, $this->userId));
		self::assertEquals($folder, $this->service->getFolder($this->collectiveId, 1, $this->userId));
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
			->willReturn(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
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

	public function testGetPagesFromFolder(): void {
		$filesNotJustMd = [];
		$filesJustMd = [];
		$pageInfos = [];

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
			->willReturn(101);
		$indexFile->method('getName')
			->willReturn('Readme.md');
		$folder->method('get')
			->willReturn($indexFile);
		$indexFile->method('getParent')
			->willReturn($folder);
		$indexFile->method('getMountPoint')
			->willReturn($mountPoint);
		$indexFile->method('getInternalPath')
			->willReturn('Collectives/testfolder/Readme.md');
		$indexFile->method('getMTime')
			->willReturn(0);
		$indexFile->method('getSize')
			->willReturn(0);
		$indexPage = new Page();
		$this->pageMapper->method('findByFileId')
			->willReturn($indexPage);
		$indexPageInfo = new PageInfo();
		$indexPageInfo->fromFile($indexFile, 1);
		$indexPageInfo->setParentId(101);
		$indexPageInfo->setTitle('testfolder');

		$filesJustMd[] = $indexPageInfo;
		$filesNotJustMd[] = $indexPageInfo;
		$pageInfos[] = $indexPageInfo;

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
			$file->method('getInternalPath')
				->willReturn('Collectives/testfolder/Readme.md');
			$file->method('getMTime')
				->willReturn(0);
			$file->method('getSize')
				->willReturn(0);

			$filesNotJustMd[] = $file;

			// Only add markdown files to $filesJustMd
			if (!NodeHelper::isPage($file)) {
				continue;
			}

			$filesJustMd[] = $file;

			$pageInfo = new PageInfo();
			$pageInfo->fromFile($file, 1);
			$pageInfo->setParentId(101);
			$pageInfos[] = $pageInfo;
		}

		$folder->method('getDirectoryListing')
			->willReturnOnConsecutiveCalls(
				$filesJustMd,
				$filesNotJustMd,
			);

		self::assertEquals($pageInfos, $this->service->getPagesFromFolder($this->collectiveId, $folder, $this->userId, true));
		self::assertEquals($pageInfos, $this->service->getPagesFromFolder($this->collectiveId, $folder, $this->userId, true));
	}

	public function testGetPageLink(): void {
		$collectiveName = 'My Collective';
		$pageInfo1 = new PageInfo();
		$pageInfo1->setId(123);
		$pageInfo1->setFilePath('page one');
		$pageInfo1->setFileName('subpage2.md');
		$pageInfo1->setTitle('subpage2');

		self::assertEquals('My%20Collective/page%20one/subpage2?fileId=123',
			$this->service->getPageLink($collectiveName, $pageInfo1));

		$pageInfo2 = new PageInfo();
		$pageInfo2->setId(124);
		$pageInfo2->setFilePath('page two/with another layer/and#spec!al_ch@rs?;');
		$pageInfo2->setFileName('Readme.md');
		$pageInfo2->setTitle('page');

		self::assertEquals('My%20Collective/page%20two/with%20another%20layer/and%23spec%21al_ch%40rs%3F%3B/page?fileId=124',
			$this->service->getPageLink($collectiveName, $pageInfo2));
	}

	public function testMatchBacklinks(): void {
		$this->config->method('getSystemValue')
			->willReturn(['nextcloud.local']);

		$pageInfo = new PageInfo();
		$pageInfo->setId(123);
		$pageInfo->setCollectivePath('Collectives/mycollective');
		$pageInfo->setFilePath('page1/pageX');
		$pageInfo->setFileName('subpage2.md');
		$pageInfo->setTitle('subpage2');

		$urlPathBase = '/apps/collectives/mycollective';
		$urlPath = $urlPathBase . '/page1/pageX/subpage2';

		// Relative link with fileId in [text](link) syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link](' . $urlPath . '?fileId=123).'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link](/index.php' . $urlPath . '?fileId=123).'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link to wrong path but correct fileId](' . $urlPathBase . '/subpage2?fileId=123).'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a relative link](pageX/subpage2?fileId=123).'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link to wrong fileId](' . $urlPath . '?fileId=345).'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a broken link(' . $urlPath . '?fileId=123).'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a broken link] (' . $urlPath . '?fileId=123).'));

		// Relative link with fileId in <link> syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with a link: <' . $urlPath . '?fileId=123>'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with a link: </index.php' . $urlPath . '?fileId=123>'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with a link to wrong path but correct fileId: <' . $urlPathBase . '/subpage2?fileId=123>'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with a relative link: <pageX/subpage2?fileId=123>.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with a link to wrong fileId <' . $urlPath . '?fileId=345>.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with a broken link: <' . $urlPath . '?fileId=123'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with a broken link: <' . $urlPath . '?fileId=123]>'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with a broken link: ' . $urlPath . '?fileId=123>'));

		// Relative link without fileId in [text](link) syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link](' . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link to wrong path](' . $urlPathBase . '/page1/subpage2) in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link to wrong webroot](/index.php/instance2' . $urlPath . ') in it.'));

		// Relative link without fileId in <link> syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with a link: <' . $urlPath . '> in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with a broken link: <[' . $urlPath . '> in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with a link to wrong path: <' . $urlPathBase . '/page1/subpage2> in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with a link to wrong webroot: </index.php/instance2' . $urlPath . '> in it.'));

		// Absolute link with fileId in [text](link) syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link](http://nextcloud.local' . $urlPath . '?fileId=123) in it.'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link](https://nextcloud.local' . $urlPath . '?fileId=123) in it.'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link](https://nextcloud.local/index.php' . $urlPath . '?fileId=123) in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link to wrong host (with fileId)](https://example.org/' . $urlPath . 'fileId=123) in it.'));

		// Absolute link without fileId in [text](link) syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link](http://nextcloud.local' . $urlPath . ') in it.'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link](https://nextcloud.local' . $urlPath . ') in it.'));
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link with many slashes](https://nextcloud.local/////' . str_replace('/', '//', $urlPath) . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a broken link](https://nextcloud.local' . $urlPath . ' in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link to wrong instance](https://nextcloud.local/instance2' . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link to wrong host](https://anothercloud.com' . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link to wrong host](anothercloud.com' . $urlPath . ') in it.'));

		OC::$WEBROOT = 'mycloud';

		// Relative link with fileId with webroot in [text](link) syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link with webroot](' . OC::$WEBROOT . $urlPath . '?fileId=123).'));
		// Relative link without fileId with webroot in [text](link) syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link with webroot](' . OC::$WEBROOT . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link with missing webroot](' . $urlPath . ') in it.'));
		// Absolute link with fileId with webroot in [text](link) syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link with webroot](http://nextcloud.local' . OC::$WEBROOT . $urlPath . '?fileId=123) in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link with missing webroot](http://nextcloud.local' . $urlPath . '?fileId=123) in it.'));
		// Absolute link without fileId with webroot in [text](link) syntax
		self::assertTrue($this->service->matchBacklinks($pageInfo, 'content with [a link with webroot](http://nextcloud.local' . OC::$WEBROOT . $urlPath . ') in it.'));
		self::assertFalse($this->service->matchBacklinks($pageInfo, 'content with [a link with missing webroot](http://nextcloud.local' . $urlPath . ') in it.'));
	}

	public function testIsAncestorOf(): void {
		// Allow testing the private function
		$reflection = new ReflectionClass($this->service);
		$method = $reflection->getMethod('isAncestorOf');
		$method->setAccessible(true);

		$pageId = 1;
		$targetId = 2;
		$targetParentId = 1;

		$pageParentFile = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$pageParentFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$pageParentFolder->method('get')
			->with(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX)
			->willReturn($pageParentFile);
		$pageParentFile->method('getParent')
			->willReturn($pageParentFolder);
		$pageFile = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$pageFile->method('getInternalPath')
			->willReturn('Page' . PageInfo::SUFFIX);
		$pageFile->method('getParent')
			->willReturn($pageParentFile);

		$this->nodeHelper->method('getFileById')
			->with($this->collectiveFolder, $targetId)
			->willReturn($pageParentFile);

		// $pageId is ancestor of $targetId
		$pageParentFile->method('getId')
			->willReturn($targetParentId);
		self::assertTrue($method->invokeArgs($this->service, [$this->collectiveFolder, $pageId, $targetId]));

		// $targetId is landing page
		$pageParentFile->method('getInternalPath')
			->willReturn(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
		self::assertFalse($method->invokeArgs($this->service, [$this->collectiveFolder, $pageId, $targetId]));
	}

	public function testMoveLandingPageFails(): void {
		$collective = new Collective();
		$collective->setName('Collective');
		$collective->setLevel(Member::LEVEL_ADMIN);
		$this->collectiveService->method('getCollective')
			->willReturn($collective);

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getInternalPath')
			->willReturn(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
		$this->nodeHelper->method('getFileById')
			->willReturn($file);

		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('Not allowed to move or copy landing page');
		$this->service->move($this->collectiveId, 2, 1, 'New title', 0, $this->userId);
	}

	public function testMovePageToItselfFails(): void {
		$collective = new Collective();
		$collective->setName('Collective');
		$collective->setLevel(Member::LEVEL_ADMIN);
		$this->collectiveService->method('getCollective')
			->willReturn($collective);

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getId')
			->willReturn(1);
		$parentFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$parentFolderIndexFile = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$parentFolderIndexFile->method('getId')
			->willReturn(2);
		$parentFolder->method('get')
			->willReturn($parentFolderIndexFile);
		$file->method('getParent')
			->willReturn($parentFolder);
		$this->nodeHelper->method('getFileById')
			->willReturn($file);

		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('Not allowed to move or copy a page to itself');
		$this->service->move($this->collectiveId, 1, 1, 'New title', 0, $this->userId);
	}
}
