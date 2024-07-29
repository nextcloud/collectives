<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Mount;

use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class CollectiveFolderManagerTest extends TestCase {
	private CollectiveFolderManager $manager;

	protected function setUp(): void {
		parent::setUp();

		$rootFolder = $this->getMockBuilder(IRootFolder::class)
			->disableOriginalConstructor()
			->getMock();
		$connection = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();
		$config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$userSession = $this->getMockBuilder(IUserSession::class)
			->disableOriginalConstructor()
			->getMock();
		$request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();

		$this->manager = new CollectiveFolderManager($rootFolder, $connection, $config, $userSession, $request);
	}

	protected function tearDown(): void {
		parent::tearDown();

		unlink('/tmp/Readme.de.md');
	}

	public function testGetLandingPagePath(): void {
		// Test for nonexistent localized landing page
		self::assertStringEndsWith('Readme.en.md', $this->manager->getLandingPagePath('/tmp', 'de'));
		self::assertStringEndsWith('Readme.en.md', $this->manager->getLandingPagePath('/tmp', 'en'));

		// Test for existent localized landing page
		file_put_contents('/tmp/Readme.de.md', 'testfile');
		self::assertStringEndsWith('Readme.de.md', $this->manager->getLandingPagePath('/tmp', 'de'));
	}
}
