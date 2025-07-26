<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Collectives\Service\AttachmentService;
use OCP\IPreview;
use PHPUnit\Framework\TestCase;

class AttachmentServiceTest extends TestCase {
	private AttachmentService $service;
	private string $userId = 'jane';
	private File $pageFile;
	private Folder $parentFolder;
	private string $attachmentFolderName = '.attachments.' . 1;

	protected function setUp(): void {
		$preview = $this->getMockBuilder(IPreview::class)
			->disableOriginalConstructor()
			->getMock();

		$this->pageFile = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$this->parentFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->parentFolder->method('nodeExists')
			->with($this->attachmentFolderName)
			->willReturn(true);
		$this->parentFolder->method('getRelativePath')
			->willReturn('/Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$attachmentFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$attachmentFile = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$attachmentFile->method('getId')
			->willReturn(2);
		$attachmentFile->method('getPath')
			->willReturn('/' . $this->userId . '/files/Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$attachmentFile->method('getInternalPath')
			->willReturn('/path/to/' . $this->attachmentFolderName . '/attachmentFile1');
		$attachmentFolder->method('getDirectoryListing')
			->willReturn([$attachmentFile]);
		$this->parentFolder->method('get')
			->with($this->attachmentFolderName)
			->willReturn($attachmentFolder);
		$this->pageFile->method('getParent')
			->willReturn($this->parentFolder);
		$this->pageFile->method('getId')
			->willReturn(1);

		$this->service = new AttachmentService($preview);
	}

	public function testGetAttachments(): void {
		$attachmentInfo = [
			'id' => 2,
			'name' => null,
			'filesize' => 0.0,
			'mimetype' => null,
			'timestamp' => null,
			'path' => '/Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1',
			'internalPath' => '/path/to/' . $this->attachmentFolderName . '/attachmentFile1',
			'hasPreview' => null,
		];

		self::assertEquals([$attachmentInfo], $this->service->getAttachments($this->pageFile, $this->parentFolder));
	}
}
