<?php

namespace Unit\Fs;

use OC\Files\Node\File;
use OCA\Collectives\Fs\UserFolderHelper;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class UserFolderHelperTest extends TestCase {
	private $collectivesFolder;
	private $userFolder;
	private $rootFolder;
	private $l10n;
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
		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10n->method('t')
			->willReturn('Collectives');

		$this->helper = new UserFolderHelper($this->rootFolder, $this->l10n);
	}

	public function testGetNameFolderExists(): void {
		$this->userFolder->method('get')
			->willReturn($this->collectivesFolder);

		self::assertEquals('Collectives', $this->helper->getName('jane'));
	}

	public function testGetNameFileExists(): void
	{
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();

		$this->userFolder->method('get')
			->willReturn($file);

		self::assertEquals('Collectives', $this->helper->getName('jane'));
	}

	public function testGetNameFolderNotExists(): void
	{
		$this->userFolder->method('get')
			->willThrowException(new NotFoundException);

		self::assertEquals('Collectives', $this->helper->getName('jane'));
	}

	public function testGetFolderFolderExists(): void {
		$this->userFolder->method('get')
			->willReturn($this->collectivesFolder);

		self::assertEquals($this->collectivesFolder, $this->helper->getFolder('jane'));
	}
}
