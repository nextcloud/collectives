<?php

namespace Unit\Fs;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
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
	private $helper;

	protected function setUp(): void {
		parent::setUp();

		$collective1 = new Collective();
		$collective1->setId(1);
		$collective2 = new Collective();
		$collective2->setId(2);

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

		$rootFolder = $this->getMockBuilder(IRootFolder::class)
			->disableOriginalConstructor()
			->getMock();
		$rootFolder->method('getUserFolder')
			->willReturn($this->userFolder);

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();
		$userManager->method('get')
			->willReturn($user);

		$l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$l10n->method('t')
			->willReturn('Collectives');
		$l10nFactory = $this->getMockBuilder(IFactory::class)
			->disableOriginalConstructor()
			->getMock();
		$l10nFactory->method('get')
			->willReturn($l10n);

		$this->helper = new UserFolderHelper($rootFolder, $userManager, $l10nFactory);
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
}
