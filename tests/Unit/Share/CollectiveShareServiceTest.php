<?php

namespace Unit\Service;

use OC\Files\Node\Folder;
use OC\Files\Node\Node;
use OC\Share20\Share;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Share\CollectiveShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\TestCase;

class CollectiveShareServiceTest extends TestCase {
	private $shareManager;
	private $userFolderHelper;
	private $collectiveShareMapper;
	private $service;

	private $userId = 'jane';
	private $collectiveId = '123';
	private $collectiveName = 'Test Collective';
	private $collectiveInfo;
	private $collectiveNode;

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

		$this->service = new CollectiveShareService(
			$this->shareManager,
			$this->userFolderHelper,
			$this->collectiveShareMapper
		);

		$collective = new Collective();
		$collective->setId($this->collectiveId);
		$this->collectiveInfo = new CollectiveInfo($collective, $this->collectiveName);
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
		$this->collectiveNode = $this->getMockBuilder(Node::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolder->method('get')
			->willReturnMap([
				[$this->collectiveName, $this->collectiveNode]
			]);
		$this->userFolderHelper->method('get')
			->willReturnMap([
				[$this->userId, $userFolder]
			]);

		$share->method('getNode')
			->willReturn($this->collectiveNode);
	}

	public function testCreateFolderShareLockedException(): void {
		$this->prepareFolderShare();
		$this->collectiveNode->method('lock')
			->willThrowException(new LockedException(''));

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('Could not create share');
		$this->service->createFolderShare($this->userId, $this->collectiveName);
	}

	public function testCreateFolderShareGenericShareException(): void {
		$this->prepareFolderShare();
		$this->collectiveNode
			->expects(self::once())
			->method('unlock');

		$this->shareManager->method('createShare')
			->willThrowException(new GenericShareException(''));

		$this->expectException(NotFoundException::class);
		$this->service->createFolderShare($this->userId, $this->collectiveName);
	}

	public function testCreateFolderShare(): void {
		$this->prepareFolderShare();
		$this->collectiveNode
			->expects(self::once())
			->method('unlock');

		$share = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$share->method('getNode')
			->willReturn($this->collectiveNode);
		$this->shareManager->method('createShare')
			->willReturn($share);

		self::assertEquals($share, $this->service->createFolderShare($this->userId, $this->collectiveName));
	}

	public function testFindShareDoesNotExistException(): void
	{
		// Return null when collective share is not found
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willThrowException(new DoesNotExistException(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId));
	}

	public function testFindShareMultipleObjectsReturnedException(): void
	{
		// Return null when more than one collective shares found
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willThrowException(new MultipleObjectsReturnedException(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId));
	}

	public function testFindShareShareNotFoundException(): void
	{
		$collectiveShare = $this->getMockBuilder(CollectiveShare::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willReturn($collectiveShare);

		// Return null when folder share is not found
		$this->shareManager->method('getShareByToken')
			->willThrowException(new ShareNotFound(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId));
	}

	public function testFindShare(): void {
		$collectiveShare = $this->getMockBuilder(CollectiveShare::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willReturn($collectiveShare);

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		self::assertEquals($collectiveShare, $this->service->findShare($this->userId, $this->collectiveId));
	}

	public function testCreateShareExistsAlready(): void {
		$collectiveShare = $this->getMockBuilder(CollectiveShare::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willReturn($collectiveShare);

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('A share for the collective exists already');
		$this->service->createShare($this->userId, $this->collectiveInfo);
	}
}
