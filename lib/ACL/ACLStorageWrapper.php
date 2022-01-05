<?php

declare(strict_types=1);

namespace OCA\Collectives\ACL;

use OC\Files\Cache\Cache;
use OC\Files\Cache\Scanner;
use OC\Files\Cache\Wrapper\CacheWrapper;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;

class ACLStorageWrapper extends Wrapper {
	/** @var int */
	private $permissions;

	/** @var bool */
	private $inShare;

	public function __construct($arguments) {
		parent::__construct($arguments);
		$this->permissions = $arguments['permissions'];
		$this->inShare = $arguments['in_share'];
	}

	/**
	 * @param int $permissions
	 *
	 * @return bool
	 */
	private function checkPermissions(int $permissions): bool {
		// if there is no read permissions, then deny everything
		if ($this->inShare) {
			// Check if owner of the share is actually allowed to share
			// $canRead = $this->permissions & (Constants::PERMISSION_READ + Constants::PERMISSION_SHARE);
			$canRead = ($this->permissions & Constants::PERMISSION_READ) &&
				($this->permissions & Constants::PERMISSION_SHARE);
		} else {
			$canRead = $this->permissions & Constants::PERMISSION_READ;
		}

		return $canRead && ($this->permissions & $permissions) === $permissions;
	}

	public function isReadable($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_READ) && parent::isReadable($path);
	}

	public function isUpdatable($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_UPDATE) && parent::isUpdatable($path);
	}

	public function isCreatable($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_CREATE) && parent::isCreatable($path);
	}

	public function isDeletable($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_DELETE) && parent::isDeletable($path);
	}

	public function isSharable($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_SHARE) && parent::isSharable($path);
	}

	public function getPermissions($path) {
		return $this->storage->getPermissions($path) & $this->permissions;
	}

	public function rename($path1, $path2): bool {
		if (strpos($path1, $path2) === 0) {
			$part = substr($path1, strlen($path2));
			// This is a renaming of the transfer file to the original file
			if (strpos($part, '.ocTransferId') === 0) {
				return $this->checkPermissions(Constants::PERMISSION_CREATE) && parent::rename($path1, $path2);
			}
		}
		$targetPermissions = $this->file_exists($path2) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions(Constants::PERMISSION_READ) &&
			$this->checkPermissions(Constants::PERMISSION_DELETE) &&
			$this->checkPermissions($targetPermissions) &&
			parent::rename($path1, $path2);
	}

	public function opendir($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::opendir($path);
	}

	public function copy($path1, $path2): bool {
		$targetPermissions = $this->file_exists($path2) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions(Constants::PERMISSION_READ) &&
			$this->checkPermissions($targetPermissions) &&
			parent::copy($path1, $path2);
	}

	public function touch($path, $mtime = null): bool {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($permissions) && parent::touch($path, $mtime);
	}

	public function mkdir($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_CREATE) && parent::mkdir($path);
	}

	public function rmdir($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_DELETE) && parent::rmdir($path);
	}

	public function unlink($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_DELETE) && parent::unlink($path);
	}

	public function file_put_contents($path, $data) {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($permissions) ? parent::file_put_contents($path, $data) : false;
	}

	public function fopen($path, $mode) {
		if ($mode === 'r' || $mode === 'rb') {
			$permissions = Constants::PERMISSION_READ;
		} else {
			$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		}
		return $this->checkPermissions($permissions) ? parent::fopen($path, $mode) : false;
	}

	public function writeStream(string $path, $stream, int $size = null): int {
		$permissions = $this->file_exists($path) ? Constants::PERMISSION_UPDATE : Constants::PERMISSION_CREATE;
		return $this->checkPermissions($permissions) ? parent::writeStream($path, $stream, $size) : 0;
	}

	public function getCache($path = '', $storage = null): Cache {
		if (!$storage) {
			$storage = $this;
		}
		$sourceCache = parent::getCache($path, $storage);
		return new CacheWrapper($sourceCache);
	}

	public function getMetaData($path): ?array {
		$data = parent::getMetaData($path);

		if ($data && isset($data['permissions'])) {
			$data['scan_permissions'] = $data['scan_permissions'] ?? $data['permissions'];
			$data['permissions'] &= $this->permissions;
		}
		return $data;
	}

	public function getScanner($path = '', $storage = null): Scanner {
		if (!$storage) {
			$storage = $this->storage;
		}
		return parent::getScanner($path, $storage);
	}

	public function is_dir($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_READ) &&
			parent::is_dir($path);
	}

	public function is_file($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_READ) &&
			parent::is_file($path);
	}

	public function stat($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::stat($path);
	}

	public function filetype($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filetype($path);
	}

	public function filesize($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filesize($path);
	}

	public function file_exists($path): bool {
		return $this->checkPermissions(Constants::PERMISSION_READ) && parent::file_exists($path);
	}

	public function filemtime($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filemtime($path);
	}

	public function file_get_contents($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::file_get_contents($path);
	}

	public function getMimeType($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getMimeType($path);
	}

	public function hash($type, $path, $raw = false) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::hash($type, $path, $raw);
	}

	public function getETag($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getETag($path);
	}

	public function getDirectDownload($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::getDirectDownload($path);
	}

	public function getDirectoryContent($directory): \Traversable {
		foreach ($this->getWrapperStorage()->getDirectoryContent($directory) as $data) {
			$data['scan_permissions'] = $data['scan_permissions'] ?? $data['permissions'];
			$data['permissions'] &= $this->permissions;

			yield $data;
		}
	}
}
