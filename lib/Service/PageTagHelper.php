<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use JsonException;

class PageTagHelper {
	/**
	 * @throws NotPermittedException
	 */
	private static function toArray(?string $tags): array {
		if ($tags === null) {
			return [];
		}

		try {
			$tagsArray = json_decode($tags, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new NotPermittedException('Invalid format of tags');
		}
		if (!is_array($tagsArray)) {
			throw new NotPermittedException('Invalid format of tags');
		}

		return $tagsArray;
	}

	/**
	 * @throws NotPermittedException
	 */
	private static function fromArray(array $tagsArray): string {
		try {
			return json_encode(array_values($tagsArray), JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new NotPermittedException('Invalid format of tags');
		}
	}

	/**
	 * @throws NotPermittedException
	 */
	private static function clean(array $tagsArray, array $collectiveTags): string {
		$cleanedTagsArray = [];
		foreach ($tagsArray as $tagId) {
			if (in_array($tagId, $collectiveTags, true)) {
				$cleanedTagsArray[] = $tagId;
			}
		}

		return self::fromArray($cleanedTagsArray);
	}

	/**
	 * @throws NotPermittedException
	 */
	public static function add(?string $tags, int $tagId, array $collectiveTags): string {
		$tagsArray = self::toArray($tags);

		if (!in_array($tagId, $tagsArray, true)) {
			$tagsArray[] = $tagId;
		}

		return self::clean($tagsArray, $collectiveTags);
	}

	/**
	 * @throws NotPermittedException
	 */
	public static function remove(?string $tags, int $tagId, array $collectiveTags): string {
		$tagsArray = self::toArray($tags);

		$key = array_search($tagId, $tagsArray, true);
		if ($key !== false) {
			unset($tagsArray[$key]);
		}

		return self::clean($tagsArray, $collectiveTags);
	}
}
