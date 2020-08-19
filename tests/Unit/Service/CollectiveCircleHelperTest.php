<?php

namespace Unit\Service;

use OCA\Unite\Db\Collective;
use OCA\Unite\Db\CollectiveMapper;
use OCA\Unite\Service\NotFoundException;
use OCA\Unite\Service\CollectiveCircleHelper;
use PHPUnit\Framework\TestCase;

class CollectiveCircleHelperTest extends TestCase {
	private $helper;
	private $userId = 'jane';

	protected function setUp(): void {
		$collective = new Collective();
		$collective->setCircleUniqueId('1234567');

		$collectiveMapper = $this->getMockBuilder(CollectiveMapper::class)
			->disableOriginalConstructor()
			->getMock();
		$collectiveMapper->method('findById')
			->willReturnMap([
				[1, $collective],
				[2, null],
				[3, $collective]
			]);

		$this->helper = new CollectiveCircleHelper($collectiveMapper);
	}

	public function testUserHasCollectiveCollectiveDoesntExist(): void {
		$this->expectException(NotFoundException::class);
		$this->helper->userHasCollective($this->userId, 2);
	}

	public function testUserHasCollectiveCircleMemberNotFound(): void {
		$this->expectException(NotFoundException::class);
		$this->helper->userHasCollective($this->userId, 3);
	}
}
