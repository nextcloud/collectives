<?php

namespace Unit\Db;

use OCA\Circles\Exceptions\CircleDoesNotExistException;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveGarbageCollector;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use PHPUnit\Framework\TestCase;

class CollectiveGarbageCollectorTest extends TestCase {
	private $collectiveList;

	public function testPurgeObsoleteCollectives(): void {
		$cruftCollective = new Collective();
		$cruftCollective->setId(1);
		$cruftCollective->setCircleUniqueId('cruftCircleId');
		$noCruftCollective = new Collective();
		$noCruftCollective->setId(2);
		$noCruftCollective->setCircleUniqueId('noCruftCircleId');
		$this->collectiveList = [$cruftCollective, $noCruftCollective];

		$mapper = $this->getMockBuilder(CollectiveMapper::class)
			->disableOriginalConstructor()
			->getMock();
		$mapper->method('getAll')
			->willReturn($this->collectiveList);
		$mapper->method('circleIdToName')
			->willReturnCallback(function ($circleId) {
				if ($circleId === 'cruftCircleId') {
					throw new CircleDoesNotExistException();
				}
				return 'noCruftCollective';
			});
		$mapper->method('delete')
			->willReturnCallback(function ($collective) {
				array_splice($this->collectiveList,
					array_search($collective, $this->collectiveList, true),
					1);
				return $collective;
			});

		$folderManager = $this->getMockBuilder(CollectiveFolderManager::class)
			->disableOriginalConstructor()
			->getMock();
		$garbageCollector = new CollectiveGarbageCollector($mapper, $folderManager);

		self::assertContains($cruftCollective, $this->collectiveList);
		$count = $garbageCollector->purgeObsoleteCollectives();
		self::assertEquals(1, $count);
		self::assertContains($noCruftCollective, $this->collectiveList);
		self::assertNotContains($cruftCollective, $this->collectiveList);
	}
}
