<?php

namespace Unit\Mount;

use OC\SystemConfig;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class CollectiveFolderManagerTest extends TestCase {
	private $manager;

	protected function setUp(): void {
		parent::setUp();

		$rootFolder = $this->getMockBuilder(IRootFolder::class)
			->disableOriginalConstructor()
			->getMock();
		$connection = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();
		$systemConfig = $this->getMockBuilder(SystemConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$nodeHelper = $this->getMockBuilder(NodeHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->manager = new CollectiveFolderManager($rootFolder, $connection, $systemConfig, $nodeHelper);
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
