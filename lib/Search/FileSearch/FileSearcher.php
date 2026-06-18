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
	private const TOKEN_CANDIDATE_LIMIT = 500;
	private const SEARCH_RESULT_LIMIT = 50;
	private const FUZZY_PREFIX_LENGTH = 3;
	private const FUZZY_MAX_DISTANCE = 1;
	private const MATCH_TYPE_EXACT = 3;
	private const MATCH_TYPE_PREFIX = 2;
	private const MATCH_TYPE_STEM = 1;

	public function __construct(
		private readonly SearchWordMapper $wordMapper,
		private readonly SearchDocMapper $docMapper,
		private readonly SearchFileMapper $fileMapper,
		private readonly WordTokenizer $tokenizer,
		private readonly ClauseTokenizer $clauseTokenizer,
		private readonly Stemmer $stemmer,
	) {
	}

	public function search(int $collectiveId, string $query): array {
		$languages = $this->fileMapper->getLanguagesByCollective($collectiveId) ?: [null];

		$tokens = null;
		foreach ($languages as $language) {
			$languageTokens = $this->tokenizer->tokenize($query, $language);
			$tokens = $tokens === null ? $languageTokens : array_intersect($tokens, $languageTokens);
		}
		$tokens = array_values($tokens ?? []);

		if (empty($tokens)) {
			return [];
		}

		$tokenResults = [];
		$fileIdSets = [];
		$lastIndex = array_key_last($tokens);

		foreach ($tokens as $index => $token) {
			$isLastToken = $index === $lastIndex;
			$result = $this->findWordIdsForToken($collectiveId, $token, $languages, $isLastToken);
			if (empty($result['wordIds'])) {
				return [];
			}

			$docs = $this->docMapper->findDocumentsByWords($collectiveId, $result['wordIds'], self::TOKEN_CANDIDATE_LIMIT);
			if (empty($docs)) {
				return [];
			}

			$tokenResults[] = ['matchType' => $result['matchType'], 'docs' => $docs];
			$fileIdSets[] = array_column($docs, 'file_id');
		}

		$matchingFileIds = array_intersect(...$fileIdSets);
		if (empty($matchingFileIds)) {
			return [];
		}

		return $this->rankResults($tokenResults, $matchingFileIds);
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
		$candidates = $this->wordMapper->findByCollectiveAndPrefix($collectiveId, $prefix, self::TOKEN_CANDIDATE_LIMIT);

		$matches = [];
		foreach ($candidates as $candidate) {
			$distance = levenshtein($candidate->getTerm(), $term);
			if ($distance <= self::FUZZY_MAX_DISTANCE) {
				$matches[] = $candidate;
			}
		}

		return $matches;
	}

	private function findWordIdsForToken(int $collectiveId, string $token, array $languages, bool $isLastToken = false): array {
		// step 1: exact match
		$exactWord = $this->wordMapper->findByCollectiveAndTerm($collectiveId, $token);
		if ($exactWord !== null && !$isLastToken) {
			return ['wordIds' => [$exactWord->getId()], 'matchType' => self::MATCH_TYPE_EXACT];
		}

		// step 2: prefix match
		$prefixWords = $this->wordMapper->findByCollectiveAndPrefix($collectiveId, $token, self::TOKEN_CANDIDATE_LIMIT);
		if ($exactWord !== null || !empty($prefixWords)) {
			$wordIds = array_unique(array_map(fn ($w) => $w->getId(), $prefixWords));
			if ($exactWord !== null && !in_array($exactWord->getId(), $wordIds)) {
				$wordIds[] = $exactWord->getId();
			}
			$matchType = $exactWord !== null ? self::MATCH_TYPE_EXACT : self::MATCH_TYPE_PREFIX;
			return ['wordIds' => $wordIds, 'matchType' => $matchType];
		}

		// step 3: stem match with fuzzy fallback
		$wordIds = [];
		foreach ($languages as $language) {
			$stem = $this->stemmer->stem($token, $language);
			$stemWords = $this->wordMapper->findByCollectiveAndStem($collectiveId, $stem)
				?: $this->fuzzySearchWord($collectiveId, $token);
			foreach ($stemWords as $word) {
				$wordIds[] = $word->getId();
			}
		}
		return ['wordIds' => array_unique($wordIds), 'matchType' => self::MATCH_TYPE_STEM];
	}

	private function rankResults(array $tokenResults, array $matchingFileIds): array {
		$fileScores = [];
		$allDocs = [];

		foreach ($tokenResults as $tokenResult) {
			foreach ($tokenResult['docs'] as $doc) {
				$fileId = $doc['file_id'];
				if (!in_array($fileId, $matchingFileIds)) {
					continue;
				}
				$fileScores[$fileId] = ($fileScores[$fileId] ?? 0)
					+ $tokenResult['matchType']
					* log1p((int)$doc['total_hits']);
				$allDocs[$fileId] ??= $doc;
			}
		}

		usort($allDocs, fn ($a, $b) => ($fileScores[$b['file_id']] ?? 0) <=> ($fileScores[$a['file_id']] ?? 0));

		return array_slice($allDocs, 0, self::SEARCH_RESULT_LIMIT);
	}
}
