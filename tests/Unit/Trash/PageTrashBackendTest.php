<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Trash;

use OCA\Collectives\Trash\PageTrashBackend;
use Test\TestCase;

class PageTrashBackendTest extends TestCase {
	public static function numberedLocationProvider(): array {
		return [
			'attachment' => ['Page/.attachments.5/a.png', 1, 'Page/.attachments.5/a (1).png'],
			'page in root' => ['Page.md', 2, 'Page (2).md'],
			'no extension' => ['Folder/Page', 1, 'Folder/Page (1)'],
			'multiple dots' => ['a.tar.gz', 1, 'a.tar (1).gz'],
		];
	}

	/**
	 * @dataProvider numberedLocationProvider
	 */
	public function testNumberedLocation(string $location, int $i, string $expected): void {
		$method = new \ReflectionMethod(PageTrashBackend::class, 'numberedLocation');
		self::assertEquals($expected, $method->invoke(null, $location, $i));
	}
}
