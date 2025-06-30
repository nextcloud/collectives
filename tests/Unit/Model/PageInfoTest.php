<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Model;

use OC\Files\Mount\MountPoint;
use OCA\Collectives\Model\PageInfo;
use OCP\Files\File;
use OCP\Files\Folder;
use PHPUnit\Framework\TestCase;

class PageInfoTest extends TestCase {
	public function testFromFile(): void {
		$fileId = 1;
		$fileTitle = 'name';
		$fileMTime = 0;
		$fileSize = 100;
		$fileName = 'name.md';
		$fileMountPoint = '/files/user/Collectives/collective/';
		$fileCollectivePath = 'Collectives/collective';
		$parentInternalPath = 'path/to/file';
		$internalPath = $parentInternalPath . '/' . $fileName;
		$userId = 'jane';

		$mountPoint = $this->getMockBuilder(MountPoint::class)
			->disableOriginalConstructor()
			->getMock();
		$mountPoint->method('getMountPoint')->willReturn($fileMountPoint);

		$parent = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$parent->method('getInternalPath')->willReturn($parentInternalPath);

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getId')->willReturn($fileId);
		$file->method('getMTime')->willReturn($fileMTime);
		$file->method('getSize')->willReturn($fileSize);
		$file->method('getName')->willReturn($fileName);
		$file->method('getMountPoint')->willReturn($mountPoint);
		$file->method('getParent')->willReturn($parent);
		$file->method('getInternalPath')->willReturn($internalPath);

		$pageInfo = new PageInfo();
		$pageInfo->fromFile($file, 1, $userId);

		self::assertEquals($fileId, $pageInfo->getId());
		self::assertEquals($fileTitle, $pageInfo->getTitle());
		self::assertEquals($fileMTime, $pageInfo->getTimestamp());
		self::assertEquals($fileSize, $pageInfo->getSize());
		self::assertEquals($fileName, $pageInfo->getFileName());
		self::assertEquals($parentInternalPath, $pageInfo->getFilePath());
		self::assertEquals(str_replace(DIRECTORY_SEPARATOR, ' - ', $parentInternalPath), $pageInfo->getFilePathString());
		self::assertEquals($fileCollectivePath, $pageInfo->getCollectivePath());
	}
}
