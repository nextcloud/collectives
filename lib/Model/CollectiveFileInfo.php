<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Model;

/**
 * Lightweight representation of a file entry from the `filecache` table.
 *
 * `path` is relative to the collective root folder (e.g. `Readme.md` or
 * `subfolder/page.md`), so it matches the semantics of `File::getInternalPath()`
 * within the jailed collective storage.
 */
class CollectiveFileInfo {
	public function __construct(
		public readonly int $fileId,
		public readonly int $storage,
		public readonly string $path,
		public readonly int $parent,
		public readonly string $name,
		public readonly int $mimetype,
		public readonly int $mimepart,
		public readonly int $size,
		public readonly int $mtime,
		public readonly int $storageMtime,
		public readonly int $encrypted,
		public readonly string $etag,
		public readonly int $permissions,
	) {
	}

	public function isPage(): bool {
		return str_ends_with($this->name, PageInfo::SUFFIX);
	}

	public function isIndexPage(): bool {
		return $this->name === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX;
	}
}
