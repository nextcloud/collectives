<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Tokenizer;

class ClauseTokenizer extends WordTokenizer {
	public function tokenize($text): array {
		$words = parent::tokenize($text);

		$tokens = [];
		$lastWord = '';
		foreach ($words as $word) {
			if (strlen($word) <= 3) {
				continue;
			}

			if ($lastWord) {
				$tokens[] = $lastWord . ' ' . $word;
			}
			$lastWord = $word;
		}

		return $tokens ?: explode(' ', $text, 2);
	}
}
