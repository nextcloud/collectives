<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Search\FileSearch\Db\SearchDocMapper;
use OCA\Collectives\Search\FileSearch\Db\SearchFileMapper;
use OCA\Collectives\Search\FileSearch\Db\SearchWordMapper;
use OCA\Collectives\Search\FileSearch\LanguageDetector\LanguageDetector;
use OCA\Collectives\Search\FileSearch\Stemmer\Stemmer;
use OCA\Collectives\Search\FileSearch\Tokenizer\WordTokenizer;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;

class FileIndexer {
	private const LANGUAGE_DETECTION_LIMIT = 2000;
	private const STEM_MAX_DISTANCE = 3;

	public function __construct(
		private readonly SearchWordMapper $wordMapper,
		private readonly SearchDocMapper $docMapper,
		private readonly SearchFileMapper $fileMapper,
		private readonly WordTokenizer $tokenizer,
		private readonly Stemmer $stemmer,
		private readonly LanguageDetector $languageDetector,
	) {
	}

	public function indexFolder(Folder $folder, int $collectiveId, bool $incremental = false): void {

		if (!$incremental) {
			$this->deleteIndexByCollective($collectiveId);
		}

		$files = $this->getDirectoryFiles($folder, true);

		foreach ($files as $file) {
			$this->indexFile($file, $collectiveId, $incremental);
		}
	}

	private function deleteIndexByCollective(int $collectiveId): void {
		$this->wordMapper->deleteByCollective($collectiveId);
		$this->docMapper->deleteByCollective($collectiveId);
		$this->fileMapper->deleteByCollective($collectiveId);
	}

	private function indexFile(File $file, int $collectiveId, bool $incremental): void {

		if ($incremental) {
			$existingFile = $this->fileMapper->findByCollectiveAndFileId($collectiveId, $file->getId());

			if ($existingFile && $existingFile->getMtime() >= $file->getMTime()) {
				return;
			}

			if ($existingFile) {
				$this->deleteFileFromIndex($collectiveId, $file->getId());
			}
		}

		try {
			$content = $file->getContent();
		} catch (\Exception) {
			return;
		}

		$language = $this->languageDetector->detect(mb_substr($content, 0, self::LANGUAGE_DETECTION_LIMIT));
		$tokens = $this->tokenizer->tokenize($content, $language);

		$termCounts = [];
		$termStems = [];
		foreach ($tokens as $token) {
			$term = mb_substr($token, 0, 50);
			$termCounts[$term] = ($termCounts[$term] ?? 0) + 1;
			$stem = $this->stemmer->stem($token, $language);
			$isUsefulStem = $stem !== $term && levenshtein($token, $stem) <= self::STEM_MAX_DISTANCE;
			$termStems[$term] = $isUsefulStem ? $stem : null;
		}

		unset($content, $tokens);

		foreach ($termCounts as $term => $hitCount) {
			try {
				/** @psalm-suppress RedundantCast */
				$word = $this->wordMapper->upsert($collectiveId, (string)$term, $termStems[$term], $hitCount, 1);
				$this->docMapper->insertDoc($collectiveId, $word->getId(), $file->getId(), $hitCount);
			} catch (\Exception) {
				continue;
			}
		}

		try {
			$this->fileMapper->insertFile($collectiveId, $file->getId(), $file->getInternalPath(), $file->getMTime(), $language);
		} catch (\Exception) {
		}
	}

	private function getDirectoryFiles(Folder $folder, bool $recursive = false): array {
		try {
			$lsNodes = $folder->getDirectoryListing();
		} catch (NotFoundException) {
			return [];
		}

		$files = [];
		$filesRecursive = [];
		foreach ($lsNodes as $node) {
			if ($recursive && $node instanceof Folder) {
				$filesRecursive[] = $this->getDirectoryFiles($node, true);
			}

			if (!$node instanceof File || !NodeHelper::isPage($node)) {
				continue;
			}

			if (str_starts_with($node->getParent()->getName(), '.attachments.')
				|| $node->getParent()->getName() === '.templates') {
				continue;
			}

			$files[] = $node;
		}

		return array_merge($files, ...$filesRecursive);
	}

	private function deleteFileFromIndex(int $collectiveId, int $fileId): void {
		$docs = $this->docMapper->findByCollectiveAndFileId($collectiveId, $fileId);

		foreach ($docs as $doc) {
			$this->wordMapper->decrementCounts($collectiveId, $doc->getWordId(), $doc->getHitCount());
			$this->docMapper->delete($doc);
		}

		$this->fileMapper->deleteByCollectiveAndFileId($collectiveId, $fileId);
	}
}
