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

	public function __construct(
		private readonly SearchWordMapper $wordMapper,
		private readonly SearchDocMapper $docMapper,
		private readonly SearchFileMapper $fileMapper,
		private readonly WordTokenizer $tokenizer,
		private readonly Stemmer $stemmer,
		private readonly LanguageDetector $languageDetector,
	) {
	}

	public function indexFolder(Folder $folder, string $circleUniqueId, bool $incremental = false): void {

		if (!$incremental) {
			$this->deleteIndexByCircle($circleUniqueId);
		}

		$files = $this->getDirectoryFiles($folder, true);

		foreach ($files as $file) {
			$this->indexFile($file, $circleUniqueId, $incremental);
		}
	}

	private function deleteIndexByCircle(string $circleUniqueId): void {
		$this->wordMapper->deleteByCircle($circleUniqueId);
		$this->docMapper->deleteByCircle($circleUniqueId);
		$this->fileMapper->deleteByCircle($circleUniqueId);
	}

	private function indexFile(File $file, string $circleUniqueId, bool $incremental): void {

		if ($incremental) {
			$existingFile = $this->fileMapper->findByCircleAndFileId($circleUniqueId, $file->getId());

			if ($existingFile && $existingFile->getMtime() >= $file->getMTime()) {
				return;
			}

			if ($existingFile) {
				$this->deleteFileFromIndex($circleUniqueId, $file->getId());
			}
		}

		try {
			$content = $file->getContent();
		} catch (\Exception) {
			return;
		}

		$language = $this->languageDetector->detect(mb_substr($content, 0, self::LANGUAGE_DETECTION_LIMIT));
		$tokens = $this->tokenizer->tokenize($content);

		$stems = [];
		foreach ($tokens as $token) {
			$stems[] = $this->stemmer->stem($token, $language);
		}

		$terms = array_count_values($stems);
		unset($content, $tokens, $stems);

		foreach ($terms as $term => $hitCount) {
			try {
				$term = mb_substr((string)$term, 0, 50);
				$word = $this->wordMapper->upsert($circleUniqueId, $term, $hitCount, 1);
				$this->docMapper->insertDoc($circleUniqueId, $word->getId(), $file->getId(), $hitCount);
			} catch (\Exception) {
				continue;
			}
		}

		try {
			$this->fileMapper->insertFile($circleUniqueId, $file->getId(), $file->getInternalPath(), $file->getMTime(), $language);
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

			$files[] = $node;
		}

		return array_merge($files, ...$filesRecursive);
	}

	private function deleteFileFromIndex(string $circleUniqueId, int $fileId): void {
		$docs = $this->docMapper->findByCircleAndFileId($circleUniqueId, $fileId);

		foreach ($docs as $doc) {
			$this->wordMapper->decrementCounts($circleUniqueId, $doc->getWordId(), $doc->getHitCount());
			$this->docMapper->delete($doc);
		}

		$this->fileMapper->deleteByCircleAndFileId($circleUniqueId, $fileId);
	}
}
