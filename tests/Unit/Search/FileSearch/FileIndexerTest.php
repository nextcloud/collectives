<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Search\FileSearch;

use OCA\Collectives\Search\FileSearch\Db\SearchDoc;
use OCA\Collectives\Search\FileSearch\Db\SearchDocMapper;
use OCA\Collectives\Search\FileSearch\Db\SearchFile;
use OCA\Collectives\Search\FileSearch\Db\SearchFileMapper;
use OCA\Collectives\Search\FileSearch\Db\SearchWordMapper;
use OCA\Collectives\Search\FileSearch\FileIndexer;
use OCA\Collectives\Search\FileSearch\LanguageDetector\LanguageDetector;
use OCA\Collectives\Search\FileSearch\Stemmer\Stemmer;
use OCA\Collectives\Search\FileSearch\Tokenizer\WordTokenizer;
use OCP\Files\File;
use OCP\Files\Folder;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FileIndexerTest extends TestCase {
	private SearchWordMapper&MockObject $wordMapper;
	private SearchDocMapper&MockObject $docMapper;
	private SearchFileMapper&MockObject $fileMapper;
	private FileIndexer $indexer;

	protected function setUp(): void {
		parent::setUp();
		$this->wordMapper = $this->createMock(SearchWordMapper::class);
		$this->docMapper = $this->createMock(SearchDocMapper::class);
		$this->fileMapper = $this->createMock(SearchFileMapper::class);
		$this->indexer = new FileIndexer(
			$this->wordMapper,
			$this->docMapper,
			$this->fileMapper,
			$this->createMock(WordTokenizer::class),
			$this->createMock(Stemmer::class),
			$this->createMock(LanguageDetector::class),
		);
	}

	public function testIncrementalIndexRemovesFilesNoLongerInFolder(): void {
		$parent = $this->createMock(Folder::class);
		$parent->method('getName')->willReturn('Collective');
		$page = $this->createMock(File::class);
		$page->method('getName')->willReturn('Readme.md');
		$page->method('getId')->willReturn(10);
		$page->method('getMTime')->willReturn(100);
		$page->method('getParent')->willReturn($parent);

		$folder = $this->createMock(Folder::class);
		$folder->method('getDirectoryListing')->willReturn([$page]);

		$indexedPage = new SearchFile();
		$indexedPage->setMtime(100);

		$doc = new SearchDoc();
		$doc->setWordId(7);
		$doc->setHitCount(2);

		$this->fileMapper->method('findFileIdsByCollective')->with(1)->willReturn([10, 42]);
		$this->fileMapper->method('findByCollectiveAndFileId')->with(1, 10)->willReturn($indexedPage);
		$this->docMapper->method('findByCollectiveAndFileId')->with(1, 42)->willReturn([$doc]);
		$this->wordMapper->expects($this->once())->method('decrementCounts')->with(1, 7, 2);
		$this->docMapper->expects($this->once())->method('delete')->with($doc);
		$this->fileMapper->expects($this->once())->method('deleteByCollectiveAndFileId')->with(1, 42);

		$this->indexer->indexFolder($folder, 1, true);
	}

	public function testIncrementalIndexKeepsIndexOnEmptyListing(): void {
		$folder = $this->createMock(Folder::class);
		$folder->method('getDirectoryListing')->willReturn([]);

		$this->fileMapper->expects($this->never())->method('findFileIdsByCollective');
		$this->fileMapper->expects($this->never())->method('deleteByCollectiveAndFileId');

		$this->indexer->indexFolder($folder, 1, true);
	}

	public function testFullIndexDoesNotLookUpRemovedFiles(): void {
		$folder = $this->createMock(Folder::class);
		$folder->method('getDirectoryListing')->willReturn([]);

		$this->fileMapper->expects($this->never())->method('findFileIdsByCollective');
		$this->fileMapper->expects($this->once())->method('deleteByCollective')->with(1);

		$this->indexer->indexFolder($folder, 1);
	}
}
