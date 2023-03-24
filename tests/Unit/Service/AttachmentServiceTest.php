<?php

namespace Unit\Service;

use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\PageService;
use OCP\IPreview;
use PHPUnit\Framework\TestCase;

class AttachmentServiceTest extends TestCase {
	private AttachmentService $service;
	private string $userId = 'jane';
	private int $collectiveId = 1;
	private string $attachmentFolderName = '.attachments.' . 1;

	protected function setUp(): void {
		$pageService = $this->getMockBuilder(PageService::class)
			->disableOriginalConstructor()
			->getMock();

		$preview = $this->getMockBuilder(IPreview::class)
			->disableOriginalConstructor()
			->getMock();

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$parentFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$parentFolder->method('nodeExists')
			->with($this->attachmentFolderName)
			->willReturn(true);
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
		$parentFolder->method('get')
			->with($this->attachmentFolderName)
			->willReturn($attachmentFolder);
		$file->method('getParent')
			->willReturn($parentFolder);
		$file->method('getId')
			->willReturn(1);
		$pageService->method('getPageFile')
			->willReturn($file);

		$this->service = new AttachmentService($pageService, $preview);
	}

	public function testGetAttachments(): void {
		$attachmentInfo = [
			'id' => 2,
			'name' => null,
			'filesize' => null,
			'mimetype' => null,
			'timestamp' => null,
			'path' => '/Collectives/x/path/to/' . $this->attachmentFolderName . '/attachmentFile1',
			'internalPath' => '/path/to/' . $this->attachmentFolderName . '/attachmentFile1',
			'hasPreview' => null,
		];

		self::assertEquals([$attachmentInfo], $this->service->getAttachments($this->collectiveId, 1, $this->userId));
	}
}
