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
use OCA\Collectives\Search\FileSearch\Tokenizer\WordTokenizer;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;

class FileIndexer {
	public function __construct(
		private SearchWordMapper $wordMapper,
		private SearchDocMapper $docMapper,
		private SearchFileMapper $fileMapper,
		private WordTokenizer $tokenizer,
		private Stemmer $stemmer,
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

		$tokens = $this->tokenizer->tokenize($content);

		$stems = [];
		foreach ($tokens as $token) {
			$stems[] = $this->stemmer->stem($token);
		}

		$terms = array_count_values($stems);
		unset($content, $tokens, $stems);

		foreach ($terms as $term => $hitCount) {
			try {
				$term = substr((string)$term, 0, 50);
				$word = $this->wordMapper->upsert($circleUniqueId, $term, $hitCount, 1);
				$this->docMapper->insertDoc($circleUniqueId, (string)$word->getId(), $file->getId(), $hitCount);
			} catch (\Exception) {
				continue;
			}
		}

		try {
			$this->fileMapper->insertFile($circleUniqueId, $file->getId(), $file->getInternalPath(), $file->getMTime());
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

			$extension = pathinfo($node->getName(), PATHINFO_EXTENSION);
			if ($node instanceof File === false || !in_array($extension, ['md', 'txt'], true)) {
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
