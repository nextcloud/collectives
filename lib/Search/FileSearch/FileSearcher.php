<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch;

use OCA\Collectives\Search\FileSearch\Db\SearchDocMapper;
use OCA\Collectives\Search\FileSearch\Db\SearchWordMapper;
use OCA\Collectives\Search\FileSearch\Stemmer\Stemmer;
use OCA\Collectives\Search\FileSearch\Tokenizer\ClauseTokenizer;
use OCA\Collectives\Search\FileSearch\Tokenizer\WordTokenizer;

class FileSearcher {
	private const DEFAULT_LIMIT = 15;
	private const FUZZY_PREFIX_LENGTH = 2;
	private const FUZZY_MAX_DISTANCE = 2;

	public function __construct(
		private SearchWordMapper $wordMapper,
		private SearchDocMapper $docMapper,
		private WordTokenizer $tokenizer,
		private Stemmer $stemmer,
	) {
	}

	public function search(string $circleId, string $query, int $limit = self::DEFAULT_LIMIT): array {
		$tokens = $this->tokenizer->tokenize($query);

		$stems = [];
		foreach ($tokens as $token) {
			$stems[] = $this->stemmer->stem($token);
		}
		$stems = array_unique($stems);

		if (empty($stems)) {
			return [];
		}

		$wordIds = [];
		foreach ($stems as $stem) {
			$word = $this->wordMapper->findByCircleAndTerm($circleId, $stem);
			$words = $word ? [$word] : $this->fuzzySearchWord($circleId, $stem);

			foreach ($words as $word) {
				$wordIds[] = $word->getId();
			}
		}

		if (empty($wordIds)) {
			return [];
		}

		return $this->docMapper->findDocumentsByWords($circleId, $wordIds, $limit);
	}

	public function rankByBigrams(string $query, array $files): array {
		$clauseTokenizer = new ClauseTokenizer();
		$phrases = $clauseTokenizer->tokenize($query);

		if (empty($phrases)) {
			return $files;
		}

		$scored = [];
		foreach ($files as $file) {
			$content = mb_strtolower($file->getContent());
			$score = 0;

			foreach ($phrases as $phrase) {
				if (empty($phrase)) {
					continue;
				}
				$count = substr_count(mb_strtolower($content), mb_strtolower($phrase));
				$score += $count;
			}

			$scored[$file->getId()] = ['file' => $file, 'score' => $score];
		}

		uasort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

		return array_map(fn ($item) => $item['file'], $scored);
	}

	private function fuzzySearchWord(string $circleId, string $term): array {
		if (mb_strlen($term) < self::FUZZY_PREFIX_LENGTH) {
			return [];
		}

		$prefix = mb_substr($term, 0, self::FUZZY_PREFIX_LENGTH);
		$candidates = $this->wordMapper->findByCircleAndPrefix($circleId, $prefix);

		$matches = [];
		foreach ($candidates as $candidate) {
			$distance = levenshtein($candidate->getTerm(), $term);
			if ($distance <= self::FUZZY_MAX_DISTANCE) {
				$matches[] = $candidate;
			}
		}

		return $matches;
	}
}
