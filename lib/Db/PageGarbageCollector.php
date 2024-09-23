<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCA\Collectives\Mount\CollectiveFolderManager;

class PageGarbageCollector {
	public function __construct(
		private PageMapper $pageMapper,
		private CollectiveFolderManager $folderManager,
	) {
	}

	public function purgeObsoletePages(): int {
		$purgeCount = 0;
		$rootFolder = $this->folderManager->getRootFolder();
		foreach ($this->pageMapper->getAll() as $page) {
			if ($rootFolder->getById($page->getFileId()) === []) {
				$purgeCount++;
				$this->pageMapper->delete($page);
			}
		}

		return $purgeCount;
	}
}
