<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\IProgressReporter;
use OCA\Collectives\Service\ImportService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageService;
use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class ImportServiceTest extends TestCase {
	private int $pageIdCounter = 0;
	private array $pageContentById = [];
	private array $resolvedAttachments = [];
	private array $verboseErrors = [];
	private ImportService $service;

	public function setUp(): void {
		parent::setUp();

		$pageService = $this->createMock(PageService::class);
		$pageService->method('createBase')->willReturnCallback(
			function (int $collectiveId, int $parentId, string $title, ?int $templateId, string $userId, ?string $defaultTitle = null, ?string $content = null) {
				$pageInfo = new PageInfo();
				$pageInfo->setId(++$this->pageIdCounter);
				$pageInfo->setTitle($title);
				$pageInfo->setParentId($parentId);
				$this->pageContentById[$this->pageIdCounter] = $content ?? '';
				return $pageInfo;
			}
		);
		$pageService->method('getOrCreate')->willReturnCallback(
			function(int $collectiveId, int $parentId, string $title, string $userId): PageInfo {
				$pageInfo = new PageInfo();
				$pageInfo->setId(++$this->pageIdCounter);
				$pageInfo->setTitle($title);
				$pageInfo->setParentId($parentId);
				return $pageInfo;
			}
		);
		// Only used for rewriting links to pages, which we don't test here
		$pageService->method('findByPath')
			->willThrowException(new NotFoundException('Not found'));
		$pageService->method('getPageFile')->willReturnCallback(
			function (int $collectiveId, int $pageId, string $userId): File {
				$file = $this->createMock(File::class);
				$file->method('getContent')->willReturn($this->pageContentById[$pageId] ?? '');
				$file->method('getId')->willReturn($pageId);
				$file->method('putContent')->willReturn(null);
				return $file;
			}
		);
		$attachmentService = $this->createMock(AttachmentService::class);
		$attachmentService->method('putAttachment')->willReturnCallback(
			function (File $pageFile, string $attachmentName, string $content): string {
				$this->resolvedAttachments[] = $attachmentName;
				return '.attachments.' . $pageFile->getId() . '/' . $attachmentName;
			}
		);
		$mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$mimeTypeDetector->method('detectPath')
			->willReturnCallback(static fn (string $path): string =>
			str_ends_with($path, '.md') ? 'text/markdown' : 'application/octet-stream'
			);
		$progressReporter = $this->createMock(IProgressReporter::class);
		$progressReporter->method('writeErrorVerbose')->willReturnCallback(
			function (string $message): void {
				$this->verboseErrors[] = $message;
			}
		);

		$collective = new Collective();
		$collective->setId(1);
		$collective->setName('Test Collective');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');

		$this->service = new ImportService(
			$pageService,
			$attachmentService,
			$mimeTypeDetector,
			$progressReporter,
			$collective,
			$user,
		);
	}

	public function testImportDirectory(): void {
		$importDir = realPath(__DIR__ . '/../../../playwright/support/fixtures/files/DokuwikiMarkdownExport/pages');
		$this->assertNotFalse($importDir, 'Import directory does not exist');

		$this->service->importDirectory($importDir, 0);

		$this->assertSame(4, $this->service->getCount());
		$this->assertCount(2, $this->resolvedAttachments);
		$this->assertContains('stegosaurus.png', $this->resolvedAttachments);
		$this->assertContains('triceratops.png', $this->resolvedAttachments);
	}

	public function testImportDirectoryBlocksPathTraversalAttachment(): void {
		$tmpBase = sys_get_temp_dir() . '/collectives_test_' . uniqid('', true);
		$importDir = $tmpBase . '/importdir';
		mkdir($importDir, 0755, true);

		$secretFile = $tmpBase . '/secret.txt';
		file_put_contents($secretFile, 'sensitive content');
		file_put_contents($importDir . '/malicious.md', '# Malicious\n\n![](../secret.txt)\n');

		try {
			$this->service->importDirectory($importDir, 0);

			$this->assertSame(1, $this->service->getCount());

			$this->assertEmpty($this->resolvedAttachments, 'Path-traversal attachment ../secret.txt must not be processed.');

			$blocked = array_values(array_filter($this->verboseErrors, static fn (string $m): bool => str_contains($m, '✗ Blocked:')));
			$this->assertNotEmpty($blocked, 'A "✗ Blocked:" message must be logged for the path-traversal attachment.');
		} finally {
			@unlink($importDir . '/malicious.md');
			@rmdir($importDir);
			@unlink($secretFile);
			@rmdir($tmpBase);
		}
	}
}
