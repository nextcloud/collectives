<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\ACL;

use OC\Files\Cache\Cache;
use OC\Files\Cache\Wrapper\CacheWrapper;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;
use OCP\Files\Cache\IScanner;
use OCP\Files\Storage\IConstructableStorage;
use Traversable;

class ACLStorageWrapper extends Wrapper implements IConstructableStorage {
	private int $permissions;
	private bool $inShare;

	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->permissions = $arguments['permissions'];
		$this->inShare = $arguments['in_share'];
	}

	protected function checkPermissions(int $permissions): bool {
		// if there is no read permissions, then deny everything
		if ($this->inShare) {
			// Check if owner of the share is actually allowed to share
			// $canRead = $this->permissions & (Constants::PERMISSION_READ + Constants::PERMISSION_SHARE);
			$canRead = ($this->permissions & Constants::PERMISSION_READ)
				&& ($this->permissions & Constants::PERMISSION_SHARE);
		} else {
			$canRead = $this->permissions & Constants::PERMISSION_READ;
		}

		return $canRead && ($this->permissions & $permissions) === $permissions;
	}

	public function isReadable(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_READ) && parent::isReadable($path);
	}

	public function isUpdatable(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_UPDATE) && parent::isUpdatable($path);
	}

	public function isCreatable(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_CREATE) && parent::isCreatable($path);
	}

	public function isDeletable(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_DELETE) && parent::isDeletable($path);
	}

	public function isSharable(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_SHARE) && parent::isSharable($path);
	}

	public function getPermissions(string $path): int {
		return $this->storage->getPermissions($path) & $this->permissions;
	}

	public function rename(string $source, string $target): bool {
		if (str_starts_with($source, $target)) {
			$part = substr($source, strlen($target));
			// This is a renaming of the transfer file to the original file
			if (str_starts_with($part, '.ocTransferId')) {
				return $this->checkPermissions(Constants::PERMISSION_CREATE) && parent::rename($source, $target);
			}
		}
		$targetPermissions = $this->file_exists($target) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions(Constants::PERMISSION_READ)
			&& $this->checkPermissions(Constants::PERMISSION_DELETE)
			&& $this->checkPermissions($targetPermissions)
			&& parent::rename($source, $target);
	}

	public function opendir(string $path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::opendir($path);
	}

	public function copy(string $source, string $target): bool {
		$targetPermissions = $this->file_exists($target) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions(Constants::PERMISSION_READ)
			&& $this->checkPermissions($targetPermissions)
			&& parent::copy($source, $target);
	}

	public function touch(string $path, ?int $mtime = null): bool {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($permissions) && parent::touch($path, $mtime);
	}

	public function mkdir(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_CREATE) && parent::mkdir($path);
	}

	public function rmdir(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_DELETE) && parent::rmdir($path);
	}

	public function unlink(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_DELETE) && parent::unlink($path);
	}

	public function file_put_contents(string $path, mixed $data): int|float|false {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($permissions) ? parent::file_put_contents($path, $data) : false;
	}

	public function fopen(string $path, string $mode) {
		if ($mode === 'r' || $mode === 'rb') {
			$permissions = Constants::PERMISSION_READ;
		} else {
			$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		}
		return $this->checkPermissions($permissions) ? parent::fopen($path, $mode) : false;
	}

	public function writeStream(string $path, $stream, ?int $size = null): int {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($permissions) ? parent::writeStream($path, $stream, $size) : 0;
	}

	public function getCache(string $path = '', $storage = null): Cache {
		if (!$storage) {
			$storage = $this;
		}
		$sourceCache = parent::getCache($path, $storage);
		return new CacheWrapper($sourceCache);
	}

	public function getMetaData(string $path): ?array {
		$data = parent::getMetaData($path);

		if ($data && isset($data['permissions'])) {
			$data['scan_permissions'] ??= $data['permissions'];
			$data['permissions'] &= $this->permissions;
		}
		return $data;
	}

	public function getScanner(string $path = '', $storage = null): IScanner {
		if (!$storage) {
			$storage = $this->storage;
		}
		return parent::getScanner($path, $storage);
	}

	public function is_dir(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_READ)
			&& parent::is_dir($path);
	}

	public function is_file(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_READ)
			&& parent::is_file($path);
	}

	public function stat(string $path): array|false {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::stat($path);
	}

	public function filetype(string $path): string|false {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filetype($path);
	}

	public function file_exists(string $path): bool {
		return $this->checkPermissions(Constants::PERMISSION_READ) && parent::file_exists($path);
	}

	public function filemtime(string $path): int|false {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filemtime($path);
	}

	public function file_get_contents(string $path): string|false {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::file_get_contents($path);
	}

	public function getMimeType(string $path): string|false {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getMimeType($path);
	}

	public function hash($type, string $path, bool $raw = false): string|false {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::hash($type, $path, $raw);
	}

	public function getETag(string $path): string|false {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getETag($path);
	}

	public function getDirectDownload(string $path): array|false {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getDirectDownload($path);
	}

	public function getDirectoryContent(string $directory): Traversable {
		foreach ($this->getWrapperStorage()->getDirectoryContent($directory) as $data) {
			$data['scan_permissions'] ??= $data['permissions'];
			$data['permissions'] &= $this->permissions;

			yield $data;
		}
	}

	public function filesize(string $path): float|false|int {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filesize($path);
	}
}
