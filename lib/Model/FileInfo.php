<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Model;

/**
 * Represents a file entry from the filecache table
 */
class FileInfo {
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
		public readonly int $storage_mtime,
		public readonly int $encrypted,
		public readonly int $unencrypted_size,
		public readonly string $etag,
		public readonly int $permissions,
		public readonly ?string $checksum,
	) {
	}

	/**
	 * Get the title from filename (strip .md suffix, handle index pages)
	 */
	public function getTitle(): string {
		if ($this->name === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX) {
			return '';
		}
		return basename($this->name, PageInfo::SUFFIX);
	}

	/**
	 * Check if this is an index page (Readme.md)
	 */
	public function isIndexPage(): bool {
		return $this->name === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX;
	}

	/**
	 * Extract relative directory path within collective folder
	 *
	 * @param string $collectiveFolderPath Collective folder filecache path (e.g., "appdata_xxx/collectives/11")
	 * @return string Relative path (e.g., "subfolder")
	 */
	public function getRelativePath(string $collectiveFolderPath): string {
		$dirPath = dirname($this->path);
		$prefix = $collectiveFolderPath . '/';

		if (str_starts_with($dirPath, $prefix)) {
			return substr($dirPath, strlen($prefix));
		}

		if ($dirPath === $collectiveFolderPath) {
			return '';
		}

		return '';
	}

	/**
	 * Check if this file is inside a hidden folder (starting with .)
	 *
	 * @param string $collectiveFolderPath Collective folder filecache path
	 * @return bool
	 */
	public function isInHiddenFolder(string $collectiveFolderPath): bool {
		$relativePath = $this->getRelativePath($collectiveFolderPath);
		if ($relativePath === '') {
			return false;
		}
		foreach (explode('/', $relativePath) as $segment) {
			if (str_starts_with($segment, '.')) {
				return true;
			}
		}
		return false;
	}
}
