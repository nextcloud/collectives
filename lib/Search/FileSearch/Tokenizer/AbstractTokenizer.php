<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Tokenizer;

abstract class AbstractTokenizer {
	public static function normalize(string $text): string {
		/*
		 * strip HTML tags
		 * use a regex as strip_tags() is too greedy
		 * and strips content between <!-- --> comments and other edge cases
		 */
		$text = preg_replace('/<[a-zA-Z\/][^>]*>/', '', $text) ?? $text;

		/*
		 * normalize mentions to make them searchable
		 * e.g. @[name](mention://user/name) -> @name
		 */
		$text = preg_replace('/@\[([^\]]+)\]\(mention:\/\/[^)]+\)/', '@$1', $text) ?? $text;

		return $text;
	}

	protected function getStopWords(string $language): array {
		$file = __DIR__ . '/StopWords/' . $language . '.php';
		if (file_exists($file)) {
			return require $file;
		}
		return [];
	}
}
