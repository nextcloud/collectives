<?php

declare(strict_types=1);

namespace OCA\Collectives\Mount;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class LazyFolder implements Folder {
	private IRootFolder $rootFolder;
	private string $rootPath;
	private ?Folder $folder = null;

	public function __construct(IRootFolder $rootFolder, string $rootPath) {
		$this->rootFolder = $rootFolder;
		$this->rootPath = $rootPath;
	}

	/**
	 * @return Folder
	 * @throws NotPermittedException
	 */
	private function getFolder(): Folder {
		if ($this->folder === null) {
			try {
				$folder = $this->rootFolder->get($this->rootPath);
				if (!$folder instanceof Folder) {
					throw new NotFoundException('Not a folder: ' . $folder->getPath());
				}
				$this->folder = $folder;
			} catch (NotFoundException $e) {
				$this->folder = $this->rootFolder->newFolder($this->rootPath);
			}
		}

		return $this->folder;
	}

	/**
	 * Magic method to first get the real rootFolder and then
	 * call $method with $args on it
	 *
	 * @param $method
	 * @param $args
	 * @return mixed
	 */
	public function __call($method, $args) {
		$folder = $this->getFolder();

		return call_user_func_array([$folder, $method], $args);
	}

	public function getMtime() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getMimetype() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getMimePart() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isEncrypted() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getType() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isShared() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isMounted() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getMountPoint() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getOwner() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getChecksum() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getExtension(): string {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getCreationTime(): int {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getUploadTime(): int {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getParentId(): int {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getMetadata(): array {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getFullPath($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getRelativePath($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isSubNode($node) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getDirectoryListing() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function get($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function nodeExists($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function newFolder($path) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function newFile($path, $content = null) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function search($query) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function searchByMime($mimetype) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function searchByTag($tag, $userId) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function searchBySystemTag(string $tagName, string $userId, int $limit = 0, int $offset = 0) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getById($id) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getFreeSpace() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isCreatable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getNonExistingName($name) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getRecent($limit, $offset = 0) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function move($targetPath) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function delete() {
		$this->__call(__FUNCTION__, func_get_args());
	}

	public function copy($targetPath) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function touch($mtime = null) {
		$this->__call(__FUNCTION__, func_get_args());
	}

	public function getStorage() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getPath() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getInternalPath() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getId() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function stat() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getSize($includeMounts = true) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getEtag() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getPermissions() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isReadable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isUpdateable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isDeletable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function isShareable() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getParent() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function getName() {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function lock($type) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function changeLock($targetType) {
		return $this->__call(__FUNCTION__, func_get_args());
	}

	public function unlock($type) {
		return $this->__call(__FUNCTION__, func_get_args());
	}
}
