<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Trash;

use OCA\Files_Trashbin\Trash\ITrashBackend;
use OCA\Files_Trashbin\Trash\TrashItem;
use OCP\Files\FileInfo;
use OCP\IUser;

class CollectivePageTrashItem extends TrashItem {
	public function __construct(
		ITrashBackend $backend,
		string $originalLocation,
		int $deletedTime,
		string $trashPath,
		FileInfo $fileInfo,
		IUser $user,
		?IUser $deletedBy,
		private string $mountPoint,
	) {
		parent::__construct($backend, $originalLocation, $deletedTime, $trashPath, $fileInfo, $user, $deletedBy);
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
