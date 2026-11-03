<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Service\AttachmentService;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IPreview;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AttachmentServiceTest extends TestCase {
	private AttachmentService $service;
	private string $userId = 'jane';
	private File $pageFile;
	private Folder $parentFolder;
	private Folder&MockObject $attachmentFolder;
	private File&MockObject $attachmentFile;
	private Folder&MockObject $userFolder;
	private string $attachmentFolderName = '.attachments.' . 1;

	protected function setUp(): void {
		parent::setUp();

		$appManager = $this->createMock(IAppManager::class);
		$userManager = $this->createMock(IUserManager::class);
		$preview = $this->createMock(IPreview::class);

		$this->pageFile = $this->createMock(File::class);
		$this->parentFolder = $this->createMock(Folder::class);
		$this->parentFolder->method('nodeExists')
			->with($this->attachmentFolderName)
			->willReturn(true);
		$this->parentFolder->method('getRelativePath')
			->willReturn('/.Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$this->attachmentFolder = $this->createMock(Folder::class);
		$this->attachmentFolder->method('getName')
			->willReturn($this->attachmentFolderName);
		$this->attachmentFolder->method('getPath')
			->willReturn('/' . $this->userId . '/files/.Collectives/x/path/to/' . $this->attachmentFolderName);
		$this->attachmentFolder->method('getRelativePath')
			->willReturnCallback(fn (string $path): string => substr($path, strlen('/' . $this->userId . '/files/.Collectives/x/path/to/' . $this->attachmentFolderName)));
		$this->attachmentFile = $this->createMock(File::class);
		$this->attachmentFile->method('getId')
			->willReturn(2);
		$this->attachmentFile->method('getPath')
			->willReturn('/' . $this->userId . '/files/.Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$this->attachmentFile->method('getParent')
			->willReturn($this->attachmentFolder);
		$this->attachmentFile->method('getInternalPath')
			->willReturn('/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$this->attachmentFile->method('getName')
			->willReturn('attachmentFile1');
		$this->attachmentFolder->method('getDirectoryListing')
			->willReturn([$this->attachmentFile]);
		$this->parentFolder->method('get')
			->with($this->attachmentFolderName)
			->willReturn($this->attachmentFolder);
		$this->pageFile->method('getParent')
			->willReturn($this->parentFolder);
		$this->pageFile->method('getId')
			->willReturn(1);

		$this->userFolder = $this->createMock(Folder::class);
		$this->userFolder->method('getRelativePath')
			->willReturnCallback(fn (string $path): string => substr($path, strlen('/' . $this->userId . '/files')));

		$this->service = new AttachmentService($appManager, $userManager, $preview);
	}

	public function testGetAttachments(): void {
		$attachmentInfo = [
			'id' => 2,
			'name' => 'attachmentFile1',
			'filesize' => null,
			'mimetype' => '',
			'timestamp' => null,
			'path' => '/.Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1',
			'internalPath' => '/path/to/' . $this->attachmentFolderName . '/attachmentFile1',
			'hasPreview' => false,
			'src' => $this->attachmentFolderName . DIRECTORY_SEPARATOR . 'attachmentFile1',
			'type' => 'text',
		];

		self::assertEquals([$attachmentInfo], $this->service->getAttachments($this->pageFile, $this->parentFolder));
	}

	public function testUploadAttachmentPathRelativeToFolder(): void {
		$this->attachmentFolder->method('nodeExists')
			->with('attachmentFile1')
			->willReturn(false);
		$this->attachmentFolder->expects(self::once())
			->method('newFile')
			->with('attachmentFile1', 'content')
			->willReturn($this->attachmentFile);

		$attachment = $this->service->uploadAttachment($this->pageFile, 'attachmentFile1', 'content', $this->userFolder);

		self::assertEquals('/.Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1', $attachment['path']);
	}

	public function testRenameAttachmentPathRelativeToFolder(): void {
		$renamedFile = $this->createMock(File::class);
		$renamedFile->method('getId')
			->willReturn(2);
		$renamedFile->method('getName')
			->willReturn('renamed');
		$renamedFile->method('getPath')
			->willReturn('/' . $this->userId . '/files/.Collectives/x/path/to/' . $this->attachmentFolderName . '/renamed');
		$renamedFile->method('getParent')
			->willReturn($this->attachmentFolder);
		$this->attachmentFolder->method('getById')
			->with(2)
			->willReturn([$this->attachmentFile]);
		$this->attachmentFile->expects(self::once())
			->method('move')
			->with('/' . $this->userId . '/files/.Collectives/x/path/to/' . $this->attachmentFolderName . '/renamed')
			->willReturn($renamedFile);

		$attachment = $this->service->renameAttachment($this->pageFile, 2, 'renamed', $this->userFolder);

		self::assertEquals('renamed', $attachment['name']);
		self::assertEquals('/.Collectives/x/path/to/' . $this->attachmentFolderName . '/renamed', $attachment['path']);
	}
}
