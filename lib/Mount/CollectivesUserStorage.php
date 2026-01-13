<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use Icewind\Streams\IteratorDirectory;
use OC\Files\Storage\Common;
use OCP\Constants;
use OCP\Files\Storage\IStorage;

/**
 * Storage for collectives user mount point
 * This storage contains no files - only other mount points can exist within it
 */
class CollectivesUserStorage extends Common {
	public function __construct($parameters) {
		parent::__construct($parameters);
	}

	private static function pathIsRoot(string $path): bool {
		return $path === '' || $path === '/';
	}

	public function getId(): string {
		return 'collectives-user';
	}

	public function mkdir(string $path): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function rmdir(string $path): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function opendir(string $path): IteratorDirectory {
		return new IteratorDirectory();
	}

	public function is_dir(string $path): bool {
		return self::pathIsRoot($path);
	}

	public function is_file(string $path): bool {
		return false;
	}

	public function stat(string $path): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function filetype(string $path): string|false {
		return self::pathIsRoot($path) ? 'dir' : false;
	}

	public function filesize(string $path): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function isCreatable(string $path): bool {
		return false;
	}

	public function isReadable(string $path): bool {
		// Root is readable
		return self::pathIsRoot($path);
	}

	public function isUpdatable(string $path): bool {
		return false;
	}

	public function isDeletable(string $path): bool {
		return false;
	}

	public function isSharable(string $path): bool {
		return false;
	}

	public function getPermissions(string $path): int {
		return self::pathIsRoot($path) ? Constants::PERMISSION_READ : 0;
	}

	public function file_exists(string $path): bool {
		return self::pathIsRoot($path);
	}

	public function filemtime(string $path): int|false {
		if (self::pathIsRoot($path)) {
			return time();
		}
		return false;
	}

	public function file_get_contents(string $path): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function file_put_contents(string $path, mixed $data): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function unlink(string $path): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function rename(string $source, string $target): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function copy(string $source, string $target): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function fopen(string $path, string $mode): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function getMimeType(string $path): string|false {
		if (self::pathIsRoot($path)) {
			return 'httpd/unix-directory';
		}
		return false;
	}

	public function hash(string $type, string $path, bool $raw = false): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function free_space(string $path): int {
		return 0;
	}

	public function touch(string $path, ?int $mtime = null): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function getLocalFile(string $path): string|false {
		return false;
	}

	public function getETag(string $path): string {
		return self::pathIsRoot($path) ? uniqid() : '';
	}

	public function isLocal(): bool {
		return false;
	}

	public function getDirectDownload(string $path): array|false {
		return false;
	}

	public function getDirectDownloadById(string $fileId): array|false {
		return false;
	}

	public function copyFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath, bool $preserveMtime = false): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): never {
		throw new \OC\ForbiddenException('This request is not allowed to access the filesystem');
	}

	public function test(): bool {
		return true;
	}

	public function getOwner(string $path): string|false {
		return self::pathIsRoot($path) ? parent::getOwner($path) : false;
	}
}
