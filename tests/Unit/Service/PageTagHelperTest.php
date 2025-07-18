<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Service;

use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageTagHelper;
use PHPUnit\Framework\TestCase;

class PageTagHelperTest extends TestCase {
	private const collectiveTags = [1, 2, 3, 4];
	private const tags = '[1,5,2,3]';

	public function testAddInvalid(): void {
		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('Invalid format of tags');

		PageTagHelper::add('string', 4, self::collectiveTags);
	}

	public function testAdd(): void {
		// Add a nonexisting valid tag
		self::assertEquals('[1,2,3,4]', PageTagHelper::add(self::tags, 4, self::collectiveTags));
		// Add a nonexisting invalid tag
		self::assertEquals('[1,2,3]', PageTagHelper::add(self::tags, 6, self::collectiveTags));
		// Add an existing valid tag
		self::assertEquals('[1,2,3]', PageTagHelper::add(self::tags, 1, self::collectiveTags));
		// Add an existing invalid tag
		self::assertEquals('[1,2,3]', PageTagHelper::add(self::tags, 5, self::collectiveTags));
	}

	public function testRemove(): void {
		// Remove a nonexisting valid tag
		self::assertEquals('[1,2,3]', PageTagHelper::remove(self::tags, 4, self::collectiveTags));
		// Remove a nonexisting invalid tag
		self::assertEquals('[1,2,3]', PageTagHelper::remove(self::tags, 6, self::collectiveTags));
		// Remove an existing valid tag
		self::assertEquals('[2,3]', PageTagHelper::remove(self::tags, 1, self::collectiveTags));
		// Remove an existing invalid tag
		self::assertEquals('[1,2,3]', PageTagHelper::remove(self::tags, 5, self::collectiveTags));
	}
}
