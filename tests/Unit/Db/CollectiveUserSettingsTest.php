<?php

namespace Unit\Db;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveUserSettings;
use PHPUnit\Framework\TestCase;

class CollectiveUserSettingsTest extends TestCase {
	public function testSetPageOrder(): void {
		$settings = new CollectiveUserSettings();
		$pageOrders = array_keys(Collective::pageOrders);

		// Test valid values (min and max of Collective::pageOrders)
		$settings->setPageOrder(min($pageOrders));
		self::assertEquals(min($pageOrders), $settings->getPageOrder());
		$settings->setPageOrder(max($pageOrders));
		self::assertEquals(max($pageOrders), $settings->getPageOrder());
	}

	public function testSetPageOrderExceptionMin(): void {
		$settings = new CollectiveUserSettings();
		$pageOrders = array_keys(Collective::pageOrders);

		// Test invalid value (lower than min of Collective::pageOrders)
		$this->expectException(\RuntimeException::class);
		$settings->setPageOrder(min($pageOrders) - 1);
	}

	public function testSetPageOrderExceptionMax(): void {
		$settings = new CollectiveUserSettings();
		$pageOrders = array_keys(Collective::pageOrders);

		// Test invalid value(bigger than max of Collective::pageOrders)
		$this->expectException(\RuntimeException::class);
		$settings->setPageOrder(max($pageOrders) + 1);
	}
}
