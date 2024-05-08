<?php

declare(strict_types=1);

namespace OCA\Collectives\Trash;

use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\TrashItem;
use OCP\Files\FileInfo;
use OCP\IUser;
use OCP\Util;

class CollectivePageTrashItem extends TrashItem {
	public function __construct(
		ITrashBackend $backend,
		string $originalLocation,
		int $deletedTime,
		string $trashPath,
		FileInfo $fileInfo,
		IUser $user,
		private string $mountPoint
	) {
		[$major] = Util::getVersion();
		if ($major < 30) {
			parent::__construct($backend, $originalLocation, $deletedTime, $trashPath, $fileInfo, $user);
		} else {
			// *TODO* Add support for deletedby to collectives trash backend table
			parent::__construct($backend, $originalLocation, $deletedTime, $trashPath, $fileInfo, $user, null);
		}
	}

	public function isRootItem(): bool {
		return substr_count($this->getTrashPath(), '/') === 2;
	}

	public function getCollectiveMountPoint(): string {
		return $this->mountPoint;
	}

	public function getTitle(): string {
		return $this->getCollectiveMountPoint() . '/' . $this->getOriginalLocation();
	}
}
