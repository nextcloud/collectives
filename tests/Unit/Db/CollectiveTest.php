<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Db;

use OCA\Collectives\Db\Collective;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
}
