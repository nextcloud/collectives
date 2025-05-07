<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Db;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveUserSettings;
use OCA\Collectives\Service\NotPermittedException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class CollectiveUserSettingsTest extends TestCase {
	// Required to test private function `CollectiveUserSettings->setSetting()`
	protected static function getPrivateSetSettingMethod(): ReflectionMethod {
		$class = new ReflectionClass(CollectiveUserSettings::class);
		$method = $class->getMethod('setSetting');
		$method->setAccessible(true);
		return $method;
	}

	public function testSetSetting(): void {
		$setSettingsMethod = self::getPrivateSetSettingMethod();
		$settings = new CollectiveUserSettings();
		$setSettingsMethod->invokeArgs($settings, ['page_order', Collective::pageOrders[0]]);
		self::assertEquals(Collective::pageOrders[0], $settings->getSetting('page_order'));
	}

	public function testSetSettingException(): void {
		$setSettingsMethod = self::getPrivateSetSettingMethod();
		$settings = new CollectiveUserSettings();
		$this->expectException(NotPermittedException::class);
		$setSettingsMethod->invokeArgs($settings, ['invalid_setting', true]);
	}

	public function testSetPageOrder(): void {
		$settings = new CollectiveUserSettings();
		$pageOrders = array_keys(Collective::pageOrders);

		// Test valid values (min and max of Collective::pageOrders)
		$settings->setPageOrder(min($pageOrders));
		self::assertEquals(min($pageOrders), $settings->getSetting('page_order'));
		$settings->setPageOrder(max($pageOrders));
		self::assertEquals(max($pageOrders), $settings->getSetting('page_order'));
	}

	public function testSetPageOrderExceptionMin(): void {
		$settings = new CollectiveUserSettings();
		$pageOrders = array_keys(Collective::pageOrders);

		// Test invalid value (lower than min of Collective::pageOrders)
		$this->expectException(NotPermittedException::class);
		$settings->setPageOrder(min($pageOrders) - 1);
	}

	public function testSetPageOrderExceptionMax(): void {
		$settings = new CollectiveUserSettings();
		$pageOrders = array_keys(Collective::pageOrders);

		// Test invalid value(bigger than max of Collective::pageOrders)
		$this->expectException(NotPermittedException::class);
		$settings->setPageOrder(max($pageOrders) + 1);
	}

	public function testSetShowMembers(): void {
		$settings = new CollectiveUserSettings();
		self::assertEquals(null, $settings->getSetting('show_members'));
		$settings->setShowMembers(true);
		self::assertEquals(true, $settings->getSetting('show_members'));
	}

	public function testSetShowRecentPages(): void {
		$settings = new CollectiveUserSettings();
		self::assertEquals(null, $settings->getSetting('show_recent_pages'));
		$settings->setShowRecentPages(true);
		self::assertEquals(true, $settings->getSetting('show_recent_pages'));
	}

	public function testSetFavoritePagesException(): void {
		$settings = new CollectiveUserSettings();

		// Test invalid value(only numbers allowed in array)
		$this->expectException(NotPermittedException::class);
		$settings->setFavoritePages([1, 'a', 3]);
	}

	public function testSetFavoritePages(): void {
		$settings = new CollectiveUserSettings();
		$settings->setFavoritePages([1, 2, 3]);
		self::assertEquals([1, 2, 3], $settings->getSetting('favorite_pages'));
	}
}
