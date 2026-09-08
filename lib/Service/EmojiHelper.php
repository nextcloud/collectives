<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

/**
 * validate emoji values before persisting them to the database (the 'emoji' columns use VARCHAR(8)) .
 */
class EmojiHelper {
	public const MAX_LENGTH = 8;
	// Extended_Pictorgraphic covers most emoji, but not:
	// - flag sequences: two regional indicator symbols (U+12F1E6..U+1F1FF)
	// - keycap sequences: [0-9#*] + optional U+FE0F + U+20E3
	private const EMOJI_REGEX = '/\p{Extended_Pictographic}|^[\x{1F1E6}-\x{1F1FF}]{2}$|^[0-9#*]\x{FE0F}?\x{20E3}$/u';
	private static ?bool $hasExtendedPictographic = null;

	/**
	 * PCRE2 < 10.35 lacks Extended_Pictographic property
	 */
	private static function supportsExtendedPictographic(): bool {
		self::$hasExtendedPictographic ??= @preg_match('/\p{Extended_Pictographic}/u', '😀') === 1;
		return self::$hasExtendedPictographic;
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	public static function assertValid(?string $emoji): void {
		if ($emoji === null || $emoji === '') {
			return;
		}

		if (preg_match('/[\x00-\x1F\x7F]/u', $emoji) === 1) {
			throw new UnprocessableEntityException('Emoji contains invalid characters');
		}

		if (!function_exists('grapheme_strlen')) {
			throw new UnprocessableEntityException('Emoji validation is not available');
		}

		// Enforce a single visual character
		if (grapheme_strlen($emoji) !== 1) {
			throw new UnprocessableEntityException('Emoji must be a single emoji character');
		}

		// Enforce a pictographic character (i.e. an emoji)
		if (self::supportsExtendedPictographic() && preg_match(self::EMOJI_REGEX, $emoji) !== 1) {
			throw new UnprocessableEntityException('Emoji is not valid');
		}

		if (mb_strlen($emoji) > self::MAX_LENGTH) {
			throw new UnprocessableEntityException(
				'Emoji is too long (maximum ' . self::MAX_LENGTH . ' characters)',
			);
		}
	}
}
