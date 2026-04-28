<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch;

use OCA\Collectives\Search\FileSearch\Db\SearchDocMapper;
use OCA\Collectives\Search\FileSearch\Db\SearchFileMapper;
use OCA\Collectives\Search\FileSearch\Db\SearchWordMapper;
use OCA\Collectives\Search\FileSearch\Stemmer\Stemmer;
use OCA\Collectives\Search\FileSearch\Tokenizer\ClauseTokenizer;
use OCA\Collectives\Search\FileSearch\Tokenizer\WordTokenizer;

class FileSearcher {
	private const SEARCH_LIMIT = 50;
	private const FUZZY_PREFIX_LENGTH = 3;
	public const FUZZY_MAX_DISTANCE = 1;

	public function __construct(
		private readonly SearchWordMapper $wordMapper,
		private readonly SearchDocMapper $docMapper,
		private readonly SearchFileMapper $fileMapper,
		private readonly WordTokenizer $tokenizer,
		private readonly ClauseTokenizer $clauseTokenizer,
		private readonly Stemmer $stemmer,
	) {
	}

	private function mergeResults(array $results, array $newResults): array {
		$existingFileIds = array_column($results, 'file_id');
		foreach ($newResults as $result) {
			if (!in_array($result['file_id'], $existingFileIds)) {
				$results[] = $result;
			}
		}
		return $results;
	}

	public function search(int $collectiveId, string $query): array {
		$tokens = $this->tokenizer->tokenize($query);
		if (empty($tokens)) {
			return [];
		}

		// step 1: exact match on term
		$exactWordIds = [];
		foreach ($tokens as $token) {
			$exactWord = $this->wordMapper->findByCollectiveAndTerm($collectiveId, $token);
			if ($exactWord !== null) {
				$exactWordIds[] = $exactWord->getId();
			}
		}

		$results = !empty($exactWordIds)
			? $this->docMapper->findDocumentsByWords($collectiveId, $exactWordIds, self::SEARCH_LIMIT)
			: [];

		$remainingLimit = self::SEARCH_LIMIT - count($results);
		if ($remainingLimit <= 0) {
			return $results;
		}

		// step 2: prefix match on term
		$prefixWordIds = [];
		foreach ($tokens as $token) {
			$prefixWords = $this->wordMapper->findByCollectiveAndPrefix($collectiveId, $token, $remainingLimit);
			foreach ($prefixWords as $word) {
				if (!in_array($word->getId(), $exactWordIds)) {
					$prefixWordIds[] = $word->getId();
				}
			}
		}

		$prefixWordIds = array_unique($prefixWordIds);
		$prefixResults = !empty($prefixWordIds)
			? $this->docMapper->findDocumentsByWords($collectiveId, $prefixWordIds, $remainingLimit)
			: [];
		$results = $this->mergeResults($results, $prefixResults);

		$remainingLimit = self::SEARCH_LIMIT - count($results);
		if ($remainingLimit <= 0) {
			return $results;
		}

		// step 3: stem match with fuzzy fallback
		$languages = $this->fileMapper->getLanguagesByCollective($collectiveId) ?: [null];
		$stemWordIds = [];
		foreach ($tokens as $token) {
			foreach ($languages as $language) {
				$stem = $this->stemmer->stem($token, $language);
				$stemWords = $this->wordMapper->findByCollectiveAndStem($collectiveId, $stem)
					?: $this->fuzzySearchWord($collectiveId, $token);
				foreach ($stemWords as $word) {
					$stemWordIds[] = $word->getId();
				}
			}
		}

		$stemWordIds = array_unique($stemWordIds);
		$stemResults = !empty($stemWordIds)
			? $this->docMapper->findDocumentsByWords($collectiveId, $stemWordIds, $remainingLimit)
			: [];

		return $this->mergeResults($results, $stemResults);
	}

	public function rankByBigrams(string $query, array $files): array {
		$phrases = $this->clauseTokenizer->tokenize($query);

		if (empty($phrases)) {
			return $files;
		}

		$scored = [];
		foreach ($files as $file) {
			try {
				$content = mb_strtolower($file->getContent());
			} catch (\Exception) {
				continue;
			}

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

	private function fuzzySearchWord(int $collectiveId, string $term): array {
		if (mb_strlen($term) < self::FUZZY_PREFIX_LENGTH) {
			return [];
		}

		$prefix = mb_substr($term, 0, self::FUZZY_PREFIX_LENGTH);
		$candidates = $this->wordMapper->findByCollectiveAndPrefix($collectiveId, $prefix, self::SEARCH_LIMIT);

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
