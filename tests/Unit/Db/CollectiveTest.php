<?php

namespace Unit\Db;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveUserSettings;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class CollectiveTest extends TestCase {
	public function testsetPageMode(): void {
		$collective = new Collective();

		$collective->setPageMode(0);
		$this->assertEquals($collective->getPageMode(), 0);

		$collective->setPageMode(1);
		$this->assertEquals($collective->getPageMode(), 1);
	}

	public function invalidPageModeProvider(): array {
		return [
			[-1],
			[2],
			[10000]
		];
	}

	/**
	 * @dataProvider invalidPageModeProvider
	 *
	 * @param int $mode
	 */
	public function testSetPageModeException(int $mode): void {
		$this->expectException(\RuntimeException::class);
		$collective = new Collective();
		$collective->setPageMode($mode);
	}
}
