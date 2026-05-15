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

		if (grapheme_strlen($emoji) !== 1) {
			throw new UnprocessableEntityException('Emoji must be a single emoji character');
		}

		if (preg_match('/\p{Extended_Pictographic}/u', $emoji) !== 1) {
			throw new UnprocessableEntityException('Emoji is not valid');
		}

		if (mb_strlen($emoji) > self::MAX_LENGTH) {
			throw new UnprocessableEntityException(
				'Emoji is too long (maximum ' . self::MAX_LENGTH . ' characters)',
			);
		}
	}
}
