<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Controller;

use OCA\Collectives\Controller\SettingsController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase {
	private SettingsController $settingsController;

	protected function setUp(): void {
		parent::setUp();

		$request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();
		$config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();

		$this->settingsController = new SettingsController(
			'collectives',
			$request,
			$config,
			'jane'
		);
	}

	public function testSetUserSettingInvalidSetting(): void {
		$this->expectException(OCSBadRequestException::class);
		$this->settingsController->setUserSetting('nonexistent', 'value');
	}

	public function testSetUserSettingsEmptyString(): void {
		$this->expectException(OCSBadRequestException::class);
		$this->settingsController->setUserSetting('user_folder', '');
	}

	public function testSetUserSettingsNoSlashStart(): void {
		$this->expectException(OCSBadRequestException::class);
		$this->settingsController->setUserSetting('user_folder', 'test');
	}

	public function testSetUserSettingsRootFolder(): void {
		$this->expectException(OCSBadRequestException::class);
		$this->settingsController->setUserSetting('user_folder', '/');
	}

	public function testSetUserSettingsSlashEnd(): void {
		$this->expectException(OCSBadRequestException::class);
		$this->settingsController->setUserSetting('user_folder', '/test/');
	}

	public function testSetUserSettings(): void {
		$response = new DataResponse(['user_folder' => '/test'], Http::STATUS_OK);
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', '/test'));
	}

	public function testSetUserSettingsSubfolder(): void {
		$response = new DataResponse(['user_folder' => '/test/abc'], Http::STATUS_OK);
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', '/test/abc'));
	}
}
