<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\ACL;

use OC;
use OC\Files\Storage\Temporary;
use OCA\Collectives\ACL\ACLStorageWrapper;
use OCP\Constants;
use OCP\Files\Storage\IStorage;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class ACLStorageWrapperTest extends TestCase {
	private IStorage $source;

	protected function setUp(): void {
		parent::setUp();

		OC::$server->registerService(IDBConnection::class, fn () => $this->createMock(IDBConnection::class));

		$this->source = new Temporary([]);
	}

	public function testNoReadImpliesNothing(): void {
		$this->source->mkdir('foo');

		$storage = new ACLStorageWrapper([
			'storage' => $this->source,
			'permissions' => Constants::PERMISSION_ALL - Constants::PERMISSION_READ,
			'in_share' => false,
		]);

		$this->assertEquals(false, $storage->isUpdatable('foo'));
		$this->assertEquals(false, $storage->isCreatable('foo'));
		$this->assertEquals(false, $storage->isDeletable('foo'));
		$this->assertEquals(false, $storage->isSharable('foo'));
	}

	public function testInShareWithoutSharingPermissions(): void {
		$this->source->mkdir('foo');

		$storage = new ACLStorageWrapper([
			'storage' => $this->source,
			'permissions' => Constants::PERMISSION_ALL - Constants::PERMISSION_SHARE,
			'in_share' => true,
		]);

		$this->assertEquals(false, $storage->isReadable('foo'));
		$this->assertEquals(false, $storage->isUpdatable('foo'));
	}

	public function testMove(): void {
		$this->source->mkdir('foo');
		$this->source->touch('file1');

		$storage = new ACLStorageWrapper([
			'storage' => $this->source,
			'permissions' => Constants::PERMISSION_READ,
			'in_share' => false,
		]);

		$this->assertFalse($storage->rename('file1', 'foo/file1'));


		$storage = new ACLStorageWrapper([
			'storage' => $this->source,
			'permissions' => Constants::PERMISSION_ALL,
			'in_share' => false,
		]);

		$this->assertTrue($storage->rename('file1', 'foo/file1'));
	}
}
