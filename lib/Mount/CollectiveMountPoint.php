<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Storage;
use OCP\Files\Mount\ISystemMountPoint;
use OCP\Files\Storage\IStorageFactory;

class CollectiveMountPoint extends MountPoint implements ISystemMountPoint {
	public function __construct(
		private ?int $folderId,
		private CollectiveFolderManager $collectiveFolderManager,
		Storage $storage,
		string $mountPoint,
		?array $arguments = null,
		?IStorageFactory $loader = null,
		?array $mountOptions = null,
		?int $mountId = null,
	) {
		parent::__construct($storage, $mountPoint, $arguments, $loader, $mountOptions, $mountId, MountProvider::class);
	}

	public function getMountType(): string {
		return 'collective';
	}

	public function getFolderId(): int {
		return $this->folderId;
	}

	public function getSourcePath(): string {
		return '/' . $this->collectiveFolderManager->getRootFolder()->getPath() . '/' . $this->getFolderId();
	}
}
