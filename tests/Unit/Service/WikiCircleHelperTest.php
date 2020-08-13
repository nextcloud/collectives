<?php

namespace Unit\Service;

use OCA\Wiki\Db\Wiki;
use OCA\Wiki\Db\WikiMapper;
use OCA\Wiki\Service\NotFoundException;
use OCA\Wiki\Service\WikiCircleHelper;
use PHPUnit\Framework\TestCase;

class WikiCircleHelperTest extends TestCase {
	private $helper;
	private $userId = 'jane';

	protected function setUp(): void {
		$wiki = new Wiki();
		$wiki->setCircleUniqueId('1234567');

		$wikiMapper = $this->getMockBuilder(WikiMapper::class)
			->disableOriginalConstructor()
			->getMock();
		$wikiMapper->method('findById')
			->willReturnMap([
				[1, $wiki],
				[2, null],
				[3, $wiki]
			]);

		$this->helper = new WikiCircleHelper($wikiMapper);
	}


	public function testUserHasWikiWikiDoesntExist(): void {
		$this->expectException(NotFoundException::class);
		$this->helper->userHasWiki($this->userId, 2);
	}

	public function testUserHasWikiCircleMemberNotFound(): void {
		$this->expectException(NotFoundException::class);
		$this->assertNull($this->helper->userHasWiki($this->userId, 3));
	}
}
