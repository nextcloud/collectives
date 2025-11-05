<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OC\App\AppManager;
use OC\Files\Mount\MountPoint;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageLinkMapper;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Db\TagMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CollectiveServiceBase;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SessionService;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IConfig;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\String\UnicodeString;

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

		$slug = new UnicodeString('free-123');
		$slugger = $this->createMock(SluggerInterface::class);
		$slugger->method('slug')->willReturn($slug);

		$tagMapper = $this->createMock(TagMapper::class);

		$pageLinkMapper = $this->createMock(PageLinkMapper::class);

		$this->service = new PageService(
			$appManager,
			$this->pageMapper,
			$this->nodeHelper,
			$this->collectiveService,
			$userFolderHelper,
			$userManager,
			$this->config,
			$container,
			$sessionService,
			$slugger,
			$tagMapper,
			$pageLinkMapper,
		);
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

	public function testGetIndexPageFile(): void {
		$indexFile = $this->createMock(File::class);

		$folder = $this->createMock(Folder::class);
		$folder->method('get')->willReturn($indexFile);

		self::assertEquals($indexFile, PageService::getIndexPageFile($folder));
	}

	public function testGetIndexPageFileNoIndex(): void {
		$folder = $this->createMock(Folder::class);
		$folder->method('get')->willThrowException(new FilesNotFoundException());

		$this->expectException(NotFoundException::class);
		PageService::getIndexPageFile($folder);
	}

	public function testGetIndexPageFileBrokenIndex(): void {
		$folder = $this->createMock(Folder::class);
		$folder->method('get')->willReturn($folder);

		$this->expectException(NotFoundException::class);
		PageService::getIndexPageFile($folder);
	}

	private function prepareFile(string $fileName, Folder $parent, MountPoint $mountPoint, int $id = 1): File {
		$file = $this->createMock(File::class);
		$file->method('getId')
			->willReturn($id);
		$file->method('getName')
			->willReturn($fileName);
		$file->method('getParent')
			->willReturn($parent);
		$file->method('getMountPoint')
			->willReturn($mountPoint);
		$file->method('getInternalPath')
			->willReturn('Collectives/testfolder/' . $fileName);
		$file->method('getMTime')
			->willReturn(0);
		$file->method('getSize')
			->willReturn(0);

		return $file;
	}

	public function testGetPagesFromFolderWithSubfolderWithoutRecurse(): void {
		$files = [];
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

		$indexFile = $this->prepareFile('Readme.md', $folder, $mountPoint, 101);
		$folder->method('get')
			->willReturn($indexFile);
		$indexPage = new Page();
		$this->pageMapper->method('findByFileId')
			->willReturn($indexPage);
		$indexPageInfo = new PageInfo();
		$indexPageInfo->fromFile($indexFile, 1);
		$indexPageInfo->setParentId(101);
		$indexPageInfo->setTitle('testfolder');

		$files[] = $indexFile;
		$pageInfos[] = $indexPageInfo;

		// Add markdown files
		$fileNameList = ['page1.md', 'page2.md'];
		foreach ($fileNameList as $fileName) {
			$file = $this->prepareFile($fileName, $folder, $mountPoint);
			$files[] = $file;

			$pageInfo = new PageInfo();
			$pageInfo->fromFile($file, 1);
			$pageInfo->setParentId(101);
			$pageInfos[] = $pageInfo;
		}

		// Add subfolder
		$subfolder = $this->createMock(Folder::class);
		$subfolder->method('getId')
			->willReturn(102);
		$subfolder->method('getName')
			->willReturn($fileName);
		$subfolder->method('getParent')
			->willReturn($folder);
		$subfolder->method('getMountPoint')
			->willReturn($mountPoint);
		$subfolder->method('getInternalPath')
			->willReturn('Collectives/testfolder/' . $fileName);
		$subfolder->method('getMTime')
			->willReturn(0);
		$subfolder->method('getSize')
			->willReturn(0);

		$subfolderIndexFile = $this->prepareFile('Readme.md', $subfolder, $mountPoint, 103);
		$subfolderIndexPageInfo = new PageInfo();
		$subfolderIndexPageInfo->fromFile($subfolderIndexFile, 1);
		$subfolderIndexPageInfo->setParentId(101);

		$subfolder->method('get')
			->with(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX)
			->willReturn($subfolderIndexFile);

		$files[] = $subfolder;
		$pageInfos[] = $subfolderIndexPageInfo;

		$folder->method('getDirectoryListing')
			->willReturn($files);

		self::assertEquals($pageInfos, $this->service->getPagesFromFolder($this->collectiveId, $folder, $this->userId));
	}

	public function testGetPagesFromFolderRecursive(): void {
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

		$indexFile = $this->prepareFile('Readme.md', $folder, $mountPoint, 101);
		$folder->method('get')
			->willReturn($indexFile);
		$indexPage = new Page();
		$this->pageMapper->method('findByFileId')
			->willReturn($indexPage);
		$indexPageInfo = new PageInfo();
		$indexPageInfo->fromFile($indexFile, 1);
		$indexPageInfo->setParentId(101);
		$indexPageInfo->setTitle('testfolder');

		$filesJustMd[] = $indexFile;
		$filesNotJustMd[] = $indexFile;
		$pageInfos[] = $indexPageInfo;

		$fileNameList = [ 'page1.md', 'page2.md', 'page3.md', 'another.jpg', 'whatever.txt' ];
		foreach ($fileNameList as $fileName) {
			// Add all files to $filesNotJustMd
			$file = $this->prepareFile($fileName, $folder, $mountPoint);

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

	public function testGetPagesFromFolderWithMissingIndex(): void {
		$files = [];
		$pageInfos = [];

		$mountPoint = $this->getMockBuilder(MountPoint::class)
			->disableOriginalConstructor()
			->getMock();
		$mountPoint->method('getMountPoint')->willReturn('/files/user/Collectives/collective/');

		$folder = $this->createMock(Folder::class);
		$folder->method('getParent')
			->willReturn($folder);
		$folder->method('getName')
			->willReturn('testfolder');

		$file1Name = 'page1.md';
		$file1 = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file1->method('getId')
			->willReturn(1);
		$file1->method('getName')
			->willReturn($file1Name);
		$file1->method('getParent')
			->willReturn($folder);
		$file1->method('getMountPoint')
			->willReturn($mountPoint);
		$file1->method('getInternalPath')
			->willReturn('Collectives/testfolder/' . $file1Name);
		$file1->method('getMTime')
			->willReturn(0);
		$file1->method('getSize')
			->willReturn(0);
		$files[] = $file1;

		$folder->method('getDirectoryListing')
			->willReturn($files);

		$pageInfo1 = new PageInfo();
		$pageInfo1->fromFile($file1, 1);
		$pageInfo1->setParentId(101);
		$pageInfos[] = $pageInfo1;

		$indexFile = $this->createMock(File::class);
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
		$folder->method('newFile')
			->willReturn($indexFile);

		$indexPageInfo = new PageInfo();
		$indexPageInfo->fromFile($indexFile, 1);
		$indexPageInfo->setParentId(101);
		$indexPageInfo->setTitle('testfolder');
		$indexPageInfo->setSlug('free-123');
		$indexPageInfo->setLastUserId('jane');
		array_unshift($pageInfos, $indexPageInfo);

		self::assertEquals($pageInfos, $this->service->getPagesFromFolder($this->collectiveId, $folder, $this->userId, true));
	}

	public function testGetPageLink(): void {
		$collectiveUrlPath = 'My-Collective-2';

		$pageInfo1 = new PageInfo();
		$pageInfo1->setId(123);
		$pageInfo1->setParentId(456);
		$pageInfo1->setFilePath('page one');
		$pageInfo1->setFileName('subpage2.md');
		$pageInfo1->setTitle('subpage2');
		$pageInfo1->setSlug('subpage2');

		self::assertEquals('My-Collective-2/subpage2-123',
			$this->service->getPageLink($collectiveUrlPath, $pageInfo1));

		$pageInfo2 = new PageInfo();
		$pageInfo2->setId(124);
		$pageInfo2->setParentId(0);
		$pageInfo2->setFilePath('');
		$pageInfo2->setFileName('Readme.md');
		$pageInfo2->setTitle('Landing page');
		$pageInfo2->setSlug('');

		self::assertEquals('My-Collective-2',
			$this->service->getPageLink($collectiveUrlPath, $pageInfo2));
	}

	public function testGetPageLinkNoSlug(): void {
		$collectiveUrlPath = 'My Collective';

		$pageInfo1 = new PageInfo();
		$pageInfo1->setId(123);
		$pageInfo1->setParentId(456);
		$pageInfo1->setFilePath('page one');
		$pageInfo1->setFileName('subpage2.md');
		$pageInfo1->setTitle('subpage2');

		self::assertEquals('My%20Collective/page%20one/subpage2?fileId=123',
			$this->service->getPageLink($collectiveUrlPath, $pageInfo1));

		$pageInfo2 = new PageInfo();
		$pageInfo2->setId(124);
		$pageInfo2->setParentId(456);
		$pageInfo2->setFilePath('page two/with another layer/and#spec!al_ch@rs?;/page');
		$pageInfo2->setFileName('Readme.md');
		$pageInfo2->setTitle('page');

		self::assertEquals('My%20Collective/page%20two/with%20another%20layer/and%23spec%21al_ch%40rs%3F%3B/page?fileId=124',
			$this->service->getPageLink($collectiveUrlPath, $pageInfo2));

		$pageInfo3 = new PageInfo();
		$pageInfo3->setId(125);
		$pageInfo3->setParentId(0);
		$pageInfo3->setFilePath('');
		$pageInfo3->setFileName('Readme.md');
		$pageInfo3->setTitle('Landing page');

		self::assertEquals('My%20Collective',
			$this->service->getPageLink($collectiveUrlPath, $pageInfo3));
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
