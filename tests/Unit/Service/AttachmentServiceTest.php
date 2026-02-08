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
use PHPUnit\Framework\TestCase;

class AttachmentServiceTest extends TestCase {
	private AttachmentService $service;
	private string $userId = 'jane';
	private File $pageFile;
	private Folder $parentFolder;
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
			->willReturn('/Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$attachmentFolder = $this->createMock(Folder::class);
		$attachmentFolder->method('getName')
			->willReturn($this->attachmentFolderName);
		$attachmentFile = $this->createMock(File::class);
		$attachmentFile->method('getId')
			->willReturn(2);
		$attachmentFile->method('getPath')
			->willReturn('/' . $this->userId . '/files/Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$attachmentFile->method('getParent')
			->willReturn($attachmentFolder);
		$attachmentFile->method('getInternalPath')
			->willReturn('/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$attachmentFile->method('getName')
			->willReturn('attachmentFile1');
		$attachmentFolder->method('getDirectoryListing')
			->willReturn([$attachmentFile]);
		$this->parentFolder->method('get')
			->with($this->attachmentFolderName)
			->willReturn($attachmentFolder);
		$this->pageFile->method('getParent')
			->willReturn($this->parentFolder);
		$this->pageFile->method('getId')
			->willReturn(1);

		$this->service = new AttachmentService($appManager, $userManager, $preview);
	}

	public function testGetAttachments(): void {
		$attachmentInfo = [
			'id' => 2,
			'name' => 'attachmentFile1',
			'filesize' => null,
			'mimetype' => '',
			'timestamp' => null,
			'path' => '/Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1',
			'internalPath' => '/path/to/' . $this->attachmentFolderName . '/attachmentFile1',
			'hasPreview' => false,
			'src' => $this->attachmentFolderName . DIRECTORY_SEPARATOR . 'attachmentFile1',
			'type' => 'text',
		];

		self::assertEquals([$attachmentInfo], $this->service->getAttachments($this->pageFile, $this->parentFolder));
	}
}
