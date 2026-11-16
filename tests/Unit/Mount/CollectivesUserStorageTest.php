<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Mount;

use OCA\Collectives\Mount\CollectivesUserStorage;
use OCP\Constants;
use OCP\Files\ForbiddenException;
use OCP\Files\Storage\IStorage;
use Test\TestCase;

class CollectivesUserStorageTest extends TestCase {
	private CollectivesUserStorage $storage;

	protected function setUp(): void {
		parent::setUp();
		$this->storage = new CollectivesUserStorage([]);
	}

	public static function forbiddenOperationsProvider(): array {
		return [
			'mkdir' => ['mkdir', ['/foo']],
			'rmdir' => ['rmdir', ['/foo']],
			'stat' => ['stat', ['/foo']],
			'filesize' => ['filesize', ['/foo']],
			'file_get_contents' => ['file_get_contents', ['/foo']],
			'file_put_contents' => ['file_put_contents', ['/foo', 'bar']],
			'unlink' => ['unlink', ['/foo']],
			'rename' => ['rename', ['/foo', '/bar']],
			'copy' => ['copy', ['/foo', '/bar']],
			'fopen' => ['fopen', ['/foo', 'r']],
			'hash' => ['hash', ['md5', '/foo']],
			'touch' => ['touch', ['/foo']],
		];
	}

	/**
	 * @dataProvider forbiddenOperationsProvider
	 */
	public function testForbiddenOperationThrows(string $method, array $arguments): void {
		try {
			$this->storage->$method(...$arguments);
			self::fail("$method did not throw");
		} catch (ForbiddenException $e) {
			self::assertFalse($e->getRetry());
		}
	}

	public function testCrossStorageOperationsThrow(): void {
		$sourceStorage = $this->createMock(IStorage::class);
		foreach (['copyFromStorage', 'moveFromStorage'] as $method) {
			try {
				$this->storage->$method($sourceStorage, 'foo', 'bar');
				self::fail("$method did not throw");
			} catch (ForbiddenException $e) {
				self::assertFalse($e->getRetry());
			}
		}
	}

	public function testRootIsReadOnlyDirectory(): void {
		self::assertTrue($this->storage->is_dir('/'));
		self::assertTrue($this->storage->file_exists(''));
		self::assertSame('dir', $this->storage->filetype('/'));
		self::assertSame(Constants::PERMISSION_READ, $this->storage->getPermissions('/'));
		self::assertSame('httpd/unix-directory', $this->storage->getMimeType('/'));
	}

	public function testNonRootPathDoesNotExist(): void {
		self::assertFalse($this->storage->is_dir('/foo'));
		self::assertFalse($this->storage->file_exists('/foo'));
		self::assertFalse($this->storage->filetype('/foo'));
		self::assertSame(0, $this->storage->getPermissions('/foo'));
		self::assertFalse($this->storage->getMimeType('/foo'));
	}
}
