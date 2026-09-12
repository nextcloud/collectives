<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Db;

use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use RuntimeException;
use Test\TestCase;

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
	 */
	public function testSetPageModeException(int $mode): void {
		$this->expectException(RuntimeException::class);
		$collective = new Collective();
		$collective->setPageMode($mode);
	}

	public function testGetDownloadPermissionLevelDefaultsToAdmin(): void {
		$collective = new Collective();
		$this->assertEquals(Member::LEVEL_ADMIN, $collective->getDownloadPermissionLevel());
	}

	public function testCanDownload(): void {
		$collective = new Collective();
		$collective->setCustomSetting(Collective::CUSTOM_SETTINGS_DOWNLOAD_PERMISSION_LEVEL, Member::LEVEL_MODERATOR);

		$collective->setLevel(Member::LEVEL_MEMBER);
		$this->assertFalse($collective->canDownload());

		$collective->setLevel(Member::LEVEL_MODERATOR);
		$this->assertTrue($collective->canDownload());

		$collective->setLevel(Member::LEVEL_ADMIN);
		$this->assertTrue($collective->canDownload());
	}
}
