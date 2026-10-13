<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Fs;

use OCA\Collectives\Fs\UserFolderHelper;
use OCP\Config\IUserConfig;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\PreConditionNotMetException;
use Test\TestCase;

class UserFolderHelperTest extends TestCase {
	private Folder $collectivesUserFolder;
	private Folder $userFolder;
	private IL10N $l10n;
	private IAppConfig $appConfig;
	private IUserConfig $userConfig;
	private UserFolderHelper $helper;

	protected function setUp(): void {
		parent::setUp();

		$this->collectivesUserFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectivesUserFolder->method('getName')
			->willReturn('.Collectives');

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

		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();
		$userManager->method('get')
			->willReturn($user);

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userConfig = $this->createMock(IUserConfig::class);

		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$l10nFactory = $this->getMockBuilder(IFactory::class)
			->disableOriginalConstructor()
			->getMock();
		$l10nFactory->method('get')
			->willReturn($this->l10n);

		$this->helper = new UserFolderHelper($rootFolder, $userManager, $this->appConfig, $l10nFactory, $this->userConfig);
	}

	public function testGetUserFolderSetting(): void {
		$this->appConfig->method('getValueString')
			->with('collectives', 'default_user_folder', '')
			->willReturn('');
		$this->userConfig->method('getValueString')
			->willReturn('');

		$this->l10n->method('t')
			->willReturn('Collectif');
		self::assertEquals('/.Collectif', $this->helper->getUserFolderSetting('jane'));

		$this->userConfig->method('setValueString')
			->willThrowException(new PreConditionNotMetException(''));
		$this->expectException(PreConditionNotMetException::class);
		$this->helper->getUserFolderSetting('jane');
	}

	public function testGetUserFolderSettingDefaultToAppSetting(): void {
		$this->appConfig->method('getValueString')
			->with('collectives', 'default_user_folder', '')
			->willReturn('/custom_folder');
		$this->userConfig->method('getValueString')
			->willReturn('/custom_folder');
		self::assertEquals('/custom_folder', $this->helper->getUserFolderSetting('jane'));
	}
}
