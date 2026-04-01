<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\LanguageDetector;

use LanguageDetection\Language;

class LanguageDetector {
	public function detect(string $text): ?string {
		if (trim($text) === '') {
			return null;
		}

		try {
			$detector = new Language();
			$result = (string)$detector->detect($text);
			return $result !== '' ? $result : null;
		} catch (\Exception) {
			return null;
		}
	}
}
