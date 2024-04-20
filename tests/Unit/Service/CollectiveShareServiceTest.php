<?php

namespace Unit\Service;

use OC\Files\Node\Folder;
use OC\Share20\Share;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\TestCase;

class CollectiveShareServiceTest extends TestCase {
	private IShareManager $shareManager;
	private UserFolderHelper $userFolderHelper;
	private CollectiveShareMapper $collectiveShareMapper;
	private CollectiveShareService $service;
	private Collective $collective;

	private string $userId = 'jane';
	private string $collectiveId = '123';
	private string $collectiveName = 'Test Collective';
	private Folder $collectiveFolder;

	protected function setUp(): void {
		$this->shareManager = $this->getMockBuilder(IShareManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->userFolderHelper = $this->getMockBuilder(UserFolderHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->collectiveShareMapper = $this->getMockBuilder(CollectiveShareMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$l10n->method('t')->willReturnArgument(0);

		$pageService = $this->createMock(PageService::class);

		$this->service = new CollectiveShareService(
			$this->shareManager,
			$this->userFolderHelper,
			$this->collectiveShareMapper,
			$pageService,
			$l10n
		);

		$this->collective = new Collective();
		$this->collective->setId($this->collectiveId);
		$this->collective->setName($this->collectiveName);
	}

	private function prepareFolderShare(): void {
		$share = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('newShare')
			->willReturn($share);

		$this->shareManager->method('shareApiAllowLinks')
			->willReturn(true);

		$userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectiveFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolder->method('get')
			->willReturnMap([
				[$this->collectiveName, $this->collectiveFolder]
			]);
		$this->userFolderHelper->method('get')
			->willReturnMap([
				[$this->userId, $userFolder]
			]);

		$share->method('getNode')
			->willReturn($this->collectiveFolder);
	}

	public function testCreateFolderShareLockedException(): void {
		$this->prepareFolderShare();
		$this->collectiveFolder->method('lock')
			->willThrowException(new LockedException(''));

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('Could not create share');
		$this->service->createFolderShare($this->userId, $this->collectiveName, 0);
	}

	public function testCreateFolderShareGenericShareException(): void {
		$this->prepareFolderShare();
		$this->collectiveFolder
			->expects(self::once())
			->method('unlock');

		$this->shareManager->method('createShare')
			->willThrowException(new GenericShareException(''));

		$this->expectException(NotFoundException::class);
		$this->service->createFolderShare($this->userId, $this->collectiveName, 0);
	}

	public function testCreateFolderShare(): void {
		$this->prepareFolderShare();
		$this->collectiveFolder
			->expects(self::once())
			->method('unlock');

		$share = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$share->method('getNode')
			->willReturn($this->collectiveFolder);
		$this->shareManager->method('createShare')
			->willReturn($share);

		self::assertEquals($share, $this->service->createFolderShare($this->userId, $this->collectiveName, 0));
	}

	public function testFindShareDoesNotExistException(): void {
		// Return null when collective share is not found
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willThrowException(new DoesNotExistException(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId, 0));
	}

	public function testFindShareMultipleObjectsReturnedException(): void {
		// Return null when more than one collective shares found
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willThrowException(new MultipleObjectsReturnedException(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId, 0));
	}

	public function testFindShareShareNotFoundException(): void {
		$collectiveShare = $this->getMockBuilder(CollectiveShare::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willReturn($collectiveShare);

		// Return null when folder share is not found
		$this->shareManager->method('getShareByToken')
			->willThrowException(new ShareNotFound(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId, 0));
	}

	public function testFindShare(): void {
		$collectiveShare = new CollectiveShare();
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willReturn($collectiveShare);
		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		self::assertEquals($collectiveShare, $this->service->findShare($this->userId, $this->collectiveId, 0));

		// Test with share write permissions
		$folderShare->method('getPermissions')
			->willReturn(15);

		$collectiveShare->setEditable(true);
		self::assertEquals($collectiveShare, $this->service->findShare($this->userId, $this->collectiveId, 0));
	}

	public function testUpdateShare(): void {
		$collectiveShare = new CollectiveShare();
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndTokenAndUser')
			->willReturn($collectiveShare);

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		// Without share write permissions
		self::assertEquals($collectiveShare, $this->service->updateShare($this->userId, $this->collective, null, 'token', false, ''));

		// With share write permissions
		$permissions = 15;
		$folderShare->method('getPermissions')
			->willReturn($permissions);
		$collectiveShare->setEditable(true);
		self::assertEquals($collectiveShare, $this->service->updateShare($this->userId, $this->collective, null, 'token', true, ''));
	}

	public function testUpdateShareWithPassword(): void {
		$collectiveShare = new CollectiveShare();
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndTokenAndUser')
			->willReturn($collectiveShare);

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		$folderShare->method('getPassword')
			->willReturn('passwordhash');
		$collectiveShare = $this->service->updateShare($this->userId, $this->collective, null, 'token', false, 'password');
		self::assertEquals('passwordhash', $collectiveShare->getPassword());
	}
}
