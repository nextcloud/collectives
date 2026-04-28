<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Tokenizer;

class WordTokenizer extends AbstractTokenizer {
	private const MIN_LENGTH = 2;
	private const MAX_LENGTH = 50;
	private const PATTERN = '/[^\p{L}\p{N}\p{Pc}\p{Pd}@]+/u';

	public function tokenize(string $text, ?string $language = null): array {
		$text = self::normalize($text);
		$text = mb_strtolower($text);
		$words = preg_split(self::PATTERN, $text, -1, PREG_SPLIT_NO_EMPTY);

		$stopwords = $language !== null ? $this->getStopWords($language) : [];
		$words = array_diff($words, $stopwords);

		return array_filter($words, function ($word) {
			$len = mb_strlen($word);
			return $len >= self::MIN_LENGTH && $len <= self::MAX_LENGTH;
		});
	}
}
