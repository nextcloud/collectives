<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use OC\Files\Mount\MountPoint;
use OCP\Files\Mount\ISystemMountPoint;
use OCP\Files\Storage\IStorageFactory;

class CollectivesUserMountPoint extends MountPoint implements ISystemMountPoint {
	public function __construct(
		string $mountPoint,
		?array $arguments = null,
		?IStorageFactory $loader = null,
		?array $mountOptions = null,
		?int $mountId = null,
	) {
		$storage = new CollectivesUserStorage([]);
		parent::__construct($storage, $mountPoint, $arguments, $loader, $mountOptions, $mountId, MountProvider::class);
	}

	public function getMountType(): string {
		return 'collectives-user';
	}
}
