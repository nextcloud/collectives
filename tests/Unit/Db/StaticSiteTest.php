<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Db;

use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Service\UnprocessableEntityException;
use Test\TestCase;
use UnexpectedValueException;

class StaticSiteTest extends TestCase {
	public function testEncodePageIdsReturnsJsonList(): void {
		$this->assertSame('[1,2,3]', StaticSite::encodePageIds([5 => 1, 7 => 2, 9 => 3]));
	}

	public function testEncodePageIdsThrowsForUnencodableValues(): void {
		$this->expectException(UnprocessableEntityException::class);
		StaticSite::encodePageIds([NAN]);
	}

	public static function inProgressProvider(): array {
		$now = 1800000000;
		return [
			'recent pending' => [StaticSite::STATUS_PENDING, $now - 60, $now, true],
			'pending at timeout' => [StaticSite::STATUS_PENDING, $now - StaticSite::PENDING_TIMEOUT, $now, true],
			'aborted pending' => [StaticSite::STATUS_PENDING, $now - StaticSite::PENDING_TIMEOUT - 1, $now, false],
			'provided' => [StaticSite::STATUS_PROVIDED, $now - 60, $now, false],
			'published' => [StaticSite::STATUS_PUBLISHED, $now - 60, $now, false],
			'failed' => [StaticSite::STATUS_FAILED, $now - 60, $now, false],
		];
	}

	/**
	 * @dataProvider inProgressProvider
	 */
	public function testIsInProgress(string $status, int $updatedAt, int $now, bool $expected): void {
		$staticSite = new StaticSite();
		$staticSite->setStatus($status);
		$staticSite->setUpdatedAt($updatedAt);

		$this->assertSame($expected, $staticSite->isInProgress($now));
	}

	public static function corruptedSelectedPagesProvider(): array {
		return [
			'invalid JSON' => ['[1,'],
			'scalar' => ['5'],
			'string' => ['"page"'],
			'null' => ['null'],
		];
	}

	/**
	 * @dataProvider corruptedSelectedPagesProvider
	 */
	public function testGetSelectedPageIdsThrowsForCorruptedValue(string $selectedPages): void {
		$staticSite = new StaticSite();
		$staticSite->setSelectedPages($selectedPages);

		$this->expectException(UnexpectedValueException::class);
		$staticSite->getSelectedPageIds();
	}

	public function testSelectedPageIdsRoundTrip(): void {
		$staticSite = new StaticSite();
		$staticSite->setSelectedPageIds([3, 1, 2]);

		$this->assertSame([3, 1, 2], $staticSite->getSelectedPageIds());
	}
}
