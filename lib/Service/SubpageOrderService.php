<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use JsonException;

class SubpageOrderService {
	/**
	 * @throws NotPermittedException
	 */
	private static function toArray(?string $subpageOrder): array {
		if ($subpageOrder === null) {
			return [];
		}

		try {
			$subpageOrderArray = json_decode($subpageOrder, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new NotPermittedException('Invalid format of subpage order');
		}
		if (!is_array($subpageOrderArray)) {
			throw new NotPermittedException('Invalid format of subpage order');
		}

		return $subpageOrderArray;
	}

	/**
	 * @throws NotPermittedException
	 */
	private static function fromArray(array $subpageOrderArray): string {
		try {
			return json_encode(array_values($subpageOrderArray), JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new NotPermittedException('Invalid format of subpage order');
		}
	}

	/**
	 * @throws NotPermittedException
	 */
	public static function verify(?string $subpageOrder): void {
		if ($subpageOrder) {
			$subpageOrderArray = self::toArray($subpageOrder);

			foreach ($subpageOrderArray as $pageId) {
				if (!is_int($pageId)) {
					throw new NotPermittedException('Invalid format of subpage order');
				}
			}
		}
	}

	/**
	 * @throws NotPermittedException
	 */
	public static function clean(?string $subpageOrder, array $childIds): string {
		$subpageOrderArray = self::toArray($subpageOrder);
		$cleanedSubpageOrderArray = [];
		foreach ($subpageOrderArray as $pageId) {
			if (in_array($pageId, $childIds, true)) {
				$cleanedSubpageOrderArray[] = $pageId;
			}
		}

		return self::fromArray($cleanedSubpageOrderArray);
	}

	/**
	 * @throws NotPermittedException
	 */
	public static function add(?string $subpageOrder, int $pageId, int $index = 0): string {
		$subpageOrderArray = self::toArray($subpageOrder);

		if ($key = array_search($pageId, $subpageOrderArray, true)) {
			// pageId already in array, remove first
			unset($subpageOrderArray[$key]);
		}

		array_splice($subpageOrderArray, $index, 0, [$pageId]);

		return self::fromArray($subpageOrderArray);
	}

	/**
	 * @throws NotPermittedException
	 */
	public static function remove(?string $subpageOrder, int $pageId): string {
		$subpageOrderArray = self::toArray($subpageOrder);

		if (false !== $key = array_search($pageId, $subpageOrderArray, true)) {
			unset($subpageOrderArray[$key]);
		}

		return self::fromArray($subpageOrderArray);
	}
}
