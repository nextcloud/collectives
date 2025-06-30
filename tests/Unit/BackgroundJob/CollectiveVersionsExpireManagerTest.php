<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace BackgroundJob;

use OC\Files\FileInfo;
use OC\User\User;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use OCA\Collectives\Versions\ExpireManager;
use OCA\Collectives\Versions\VersionsBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Test\TestCase;

class CollectiveVersionsExpireManagerTest extends TestCase {
	private VersionsBackend|MockObject $versionsBackend;

	public function setUp(): void {
		$this->versionsBackend = $this->createMock(VersionsBackend::class);
	}

	public function testEventDispatcherFallback() {
		$appContainer = $this->createMock(ContainerInterface::class);
		$appContainer->method('get')
			->with(VersionsBackend::class)
			->willReturn($this->versionsBackend);

		$file = $this->createMock(FileInfo::class);
		$file->method('getPath')->willReturn('/path/to/file.txt');
		$file->method('getName')->willReturn('file.txt');
		$this->versionsBackend->method('getAllVersionedFiles')
			->willReturn([$file]);
		$this->versionsBackend->expects($this->once())
			->method('getVersionsForFile')
			->willReturnCallback(function ($user, $passedFile) use ($file) {
				self::assertEquals($file, $passedFile);
				self::assertInstanceOf(User::class, $user);
				return [];
			});

		$manager = new CollectiveVersionsExpireManager(
			$appContainer,
			$this->createMock(CollectiveFolderManager::class),
			$this->createMock(ExpireManager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(CollectiveMapper::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(IEventDispatcher::class),
		);
		$manager->expireFolder(['id' => 123]);
	}
}
