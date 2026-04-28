<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\LanguageDetector;

use LanguageDetection\Language;

class LanguageDetector {

	public function __construct(
		private readonly Language $detector,
	) {
	}

	public function detect(string $text): ?string {
		if (trim($text) === '') {
			return null;
		}

		try {
			$result = (string)$this->detector->detect($text);
			return $result !== '' ? $result : null;
		} catch (\Exception) {
			return null;
		}
	}
}
