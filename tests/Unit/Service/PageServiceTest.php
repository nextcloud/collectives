<?php

namespace Unit\Service;

use OC\Files\Mount\MountPoint;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageFile;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageDoesNotExistException;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\AlreadyExistsException;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase {
	private $collectiveFolder;
	private $service;
	private $userId = 'jane';
	private $collective;

	protected function setUp(): void {
		$pageMapper = $this->getMockBuilder(PageMapper::class)
			->disableOriginalConstructor()
			->getMock();
		$pageMapper->method('findByFileId')
			->willReturn(null);

		$nodeHelper = $this->getMockBuilder(NodeHelper::class)
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

		$this->service = new PageService($pageMapper, $nodeHelper, $collectiveMapper, $userFolderHelper);

		$this->collective = new Collective();
		$this->collective->setCircleUniqueId('circleUniqueId');
	}

	public function testIsPage(): void {
		$mountPoint = $this->getMockBuilder(MountPoint::class)
			->disableOriginalConstructor()
			->getMock();
		$mountPoint->method('getMountPoint')->willReturn('');

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getName')
			->willReturnOnConsecutiveCalls(
				'page.md', 'image.jpg'
			);
		$file->method('getMountPoint')
			->willReturn($mountPoint);

		self::assertTrue($this->service->isPage($file));
		self::assertFalse($this->service->isPage($file));
	}

	public function testFindAll(): void {
		$fileNameList = [ 'page1.md', 'page2.md', 'page3.md', 'image.png', 'text.txt' ];
		$filesNotJustMd = [];
		$filesJustMd = [];
		$pageFiles = [];
		foreach ($fileNameList as $fileName) {
			$mountPoint = $this->getMockBuilder(MountPoint::class)
				->disableOriginalConstructor()
				->getMock();
			$mountPoint->method('getMountPoint')->willReturn('/files/user/Collectives/collective/');

			// Add all files to $filesNotJustMd
			$file = $this->getMockBuilder(File::class)
				->disableOriginalConstructor()
				->getMock();
			$file->method('getId')
				->willReturn(1);
			$file->method('getName')
				->willReturn($fileName);
			$file->method('getMountPoint')
				->willReturn($mountPoint);
			$filesNotJustMd[] = $file;

			// Only add markdown files to $filesJustMd
			if (!$this->service->isPage($file)) {
				continue;
			}
			$filesJustMd[] = $file;

			$pageFile = new PageFile();
			$pageFile->fromFile($file, null);
			$pageFiles[] = $pageFile;
		}

		$this->collectiveFolder->method('getDirectoryListing')
			->willReturnOnConsecutiveCalls(
				$filesJustMd,
				$filesNotJustMd
			);

		self::assertEquals($pageFiles, $this->service->findAll($this->userId, $this->collective));
		self::assertEquals($pageFiles, $this->service->findAll($this->userId, $this->collective));
	}

	public function testHandleExceptionDoesNotExistException(): void {
		self::assertInstanceOf(NotFoundException::class,
			$this->service->handleException(new DoesNotExistException('msg')));
	}

	public function testHandleExceptionMultipleObjectsReturnedException(): void {
		self::assertInstanceOf(NotFoundException::class,
			$this->service->handleException(new MultipleObjectsReturnedException('msg')));
	}

	public function testHandleExceptionAlreadyExistsException(): void {
		self::assertInstanceOf(NotFoundException::class,
			$this->service->handleException(new AlreadyExistsException('msg')));
	}

	public function testHandleExceptionPageDoesNotExistException(): void {
		self::assertInstanceOf(NotFoundException::class,
			$this->service->handleException(new PageDoesNotExistException('msg')));
	}

	public function testHandleExceptionOtherException(): void {
		self::assertInstanceOf(\RuntimeException::class,
			$this->service->handleException(new \RuntimeException('msg')));
	}
}
