<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace BackgroundJob;

use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use OCA\Collectives\Versions\ExpireManager;
use OCA\Collectives\Versions\VersionsBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\FileInfo;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Test\TestCase;

class CollectiveVersionsExpireManagerTest extends TestCase {
	private VersionsBackend|MockObject $versionsBackend;

	public function setUp(): void {
		parent::setUp();
		$this->versionsBackend = $this->createMock(VersionsBackend::class);
	}

	public function testExpireFolderUsesDummyUser(): void {
		$appContainer = $this->createMock(ContainerInterface::class);
		$appContainer->method('get')
			->with(VersionsBackend::class)
			->willReturn($this->versionsBackend);

		$dummyUser = $this->createMock(IUser::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->expects($this->once())
			->method('getExistingUser')
			->with('')
			->willReturn($dummyUser);

		$file = $this->createMock(FileInfo::class);
		$file->method('getPath')->willReturn('/path/to/file.txt');
		$file->method('getName')->willReturn('file.txt');
		$this->versionsBackend->method('getAllVersionedFiles')
			->willReturn([$file]);
		$this->versionsBackend->expects($this->once())
			->method('getVersionsForFile')
			->with($dummyUser, $file)
			->willReturn([]);

		$manager = new CollectiveVersionsExpireManager(
			$appContainer,
			$this->createMock(CollectiveFolderManager::class),
			$this->createMock(ExpireManager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(CollectiveMapper::class),
			$this->createMock(ITimeFactory::class),
			$userManager,
		);
		$manager->expireFolder(['id' => 123]);
	}
}
