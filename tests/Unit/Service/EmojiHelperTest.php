<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Service\EmojiHelper;
use OCA\Collectives\Service\UnprocessableEntityException;
use PHPUnit\Framework\TestCase;

class EmojiHelperTest extends TestCase {
	public function testNullAndEmptyAreValid(): void {
		EmojiHelper::assertValid(null);
		EmojiHelper::assertValid('');
		self::assertTrue(true);
	}

	public function testSingleEmojiIsValid(): void {
		EmojiHelper::assertValid('👍');
		EmojiHelper::assertValid('🃏');
		self::assertTrue(true);
	}

	public function testEmojiWithSkinToneIsValid(): void {
		EmojiHelper::assertValid('👍🏿');
		self::assertTrue(true);
	}

	public function testRejectsMultipleEmojis(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('single emoji');
		EmojiHelper::assertValid('😀😀😀');
	}

	public function testRejectsEmojiWithTrailingText(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('single emoji');
		EmojiHelper::assertValid('👍5fg%');
	}

	public function testRejectsPlainText(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('single emoji');
		EmojiHelper::assertValid('hello');
	}

	public function testRejectsSingleLetter(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('not valid');
		EmojiHelper::assertValid('a');
	}

	public function testRejectsNewlines(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('invalid characters');
		EmojiHelper::assertValid("👍\naaaaaaaa");
	}

	public function testRejectsControlCharacters(): void {
		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('invalid characters');
		EmojiHelper::assertValid("👍\x07");
	}
}
