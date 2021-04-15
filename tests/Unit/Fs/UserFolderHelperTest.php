<?php

namespace Unit\Fs;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OC\Files\View;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\UserFolderHelper;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

class UserFolderHelperTest extends TestCase {
	private $collectivesUserFolder;
	private $userFolder;
	private $rootFolder;
	private $userManager;
	private $l10nFactory;
	private $helper;
	private $collective1;
	private $collective2;
	private $userId = 'jane';

	protected function setUp(): void {
		parent::setUp();

		$this->collective1 = new Collective();
		$this->collective1->setId(1);
		$this->collective2 = new Collective();
		$this->collective2->setId(2);

		$this->collectivesUserFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectivesUserFolder->method('getName')
			->willReturn('Collectives');

		$this->userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->method('newFolder')
			->willReturn($this->collectivesUserFolder);
		$this->userFolder->method('get')
			->willReturn($this->collectivesUserFolder);

		$this->rootFolder = $this->getMockBuilder(IRootFolder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->rootFolder->method('getUserFolder')
			->willReturn($this->userFolder);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userManager->method('get')
			->willReturn($user);

		$l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$l10n->method('t')
			->willReturn('Collectives');
		$this->l10nFactory = $this->getMockBuilder(IFactory::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10nFactory->method('get')
			->willReturn($l10n);

		$this->helper = new UserFolderHelper($this->rootFolder, $this->userManager, $this->l10nFactory);
	}

	public function testGetFolderExists(): void {
		$this->userFolder->method('get')
			->willReturn($this->collectivesUserFolder);

		self::assertEquals($this->collectivesUserFolder, $this->helper->get('jane'));
	}

	public function testGetFileExists(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();

		$this->userFolder->method('get')
			->willReturn($file);

		self::assertEquals($this->collectivesUserFolder, $this->helper->get('jane'));
	}

	public function testGetFolderNotExists(): void {
		$this->userFolder->method('get')
			->willThrowException(new NotFoundException);

		self::assertEquals($this->collectivesUserFolder, $this->helper->get('jane'));
	}

	public function testGetCollectiveFolder(): void {
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()
			->getMock();
		$folder = new Folder('', $view, '/path/to/Folder');
		$this->collectivesUserFolder->method('get')
			->willReturn($folder);

		self::assertEquals($folder, $this->helper->getCollectiveFolder('collective1', $this->userId));
	}

	public function testGetCollectiveFolderNotFoundException(): void {
		$this->collectivesUserFolder->method('get')
			->willThrowException(new NotFoundException);

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage("Folder not found for collective collective2");
		$this->helper->getCollectiveFolder('collective2', $this->userId);
	}
}
