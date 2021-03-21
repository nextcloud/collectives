<?php

namespace Unit\Fs;

use OC\Files\Node\File;
use OCA\Collectives\Fs\UserFolderHelper;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;

class UserFolderHelperTest extends TestCase {
	private $collectivesFolder;
	private $userFolder;
	private $rootFolder;
	private $userManager;
	private $l10nFactory;
	private $helper;

	protected function setUp(): void {
		parent::setUp();

		$this->collectivesFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectivesFolder->method('getName')
			->willReturn('Collectives');

		$this->userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userFolder->method('newFolder')
			->willReturn($this->collectivesFolder);

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
			->willReturn($this->collectivesFolder);

		self::assertEquals($this->collectivesFolder, $this->helper->get('jane'));
	}

	public function testGetFileExists(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();

		$this->userFolder->method('get')
			->willReturn($file);

		self::assertEquals($this->collectivesFolder, $this->helper->get('jane'));
	}

	public function testGetFolderNotExists(): void {
		$this->userFolder->method('get')
			->willThrowException(new NotFoundException);

		self::assertEquals($this->collectivesFolder, $this->helper->get('jane'));
	}
}
