<?php

namespace Unit\Controller;

use OCA\Collectives\Controller\SettingsController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SettingsControllerTest extends TestCase {
	private $settingsController;

	protected function setUp(): void {
		parent::setUp();

		$request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();
		$config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$logger = $this->getMockBuilder(LoggerInterface::class)
			->disableOriginalConstructor()
			->getMock();

		$this->settingsController = new SettingsController(
			'collectives',
			$request,
			$config,
			$logger,
			'jane'
		);
	}

	public function testSetUserSettingInvalidSetting(): void {
		$response = new DataResponse('Unsupported setting nonexistent', Http::STATUS_BAD_REQUEST);
		self::assertEquals($response, $this->settingsController->setUserSetting('nonexistent', 'value'));
	}

	public function testSetUserSettingsEmptyValue(): void {
		$response = new DataResponse('Empty value for setting user_folder', Http::STATUS_BAD_REQUEST);
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', null));
	}

	public function testSetUserSettingsEmptyString(): void {
		$response = new DataResponse('Invalid collectives folder path', Http::STATUS_BAD_REQUEST);
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', ''));
	}

	public function testSetUserSettingsNoSlashStart(): void {
		$response = new DataResponse('Invalid collectives folder path', Http::STATUS_BAD_REQUEST);
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', 'test'));
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', '/test/'));
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', 'test/'));
	}

	public function testSetUserSettingsRootFolder(): void {
		$response = new DataResponse('Invalid collectives folder path', Http::STATUS_BAD_REQUEST);
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', '/'));
	}

	public function testSetUserSettingsMultipleSlashes(): void {
		$response = new DataResponse('Invalid collectives folder path', Http::STATUS_BAD_REQUEST);
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', '/test/'));
	}

	public function testSetUserSettings(): void {
		$response = new DataResponse('/test', Http::STATUS_OK);
		self::assertEquals($response, $this->settingsController->setUserSetting('user_folder', '/test'));
	}
}
