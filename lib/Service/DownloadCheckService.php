<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Fs\UserFolderHelper;

/**
 * Shared logic to determine whether a download (single file or archive) would
 * include the contents of a collective which has downloads disabled.
 *
 * The WebDAV PropFindPlugin only hides the download button in the UI and cannot
 * stop server-side downloads (e.g. downloading a parent folder that contains a
 * collective mount, or a single file inside such a collective).
 */
class DownloadCheckService {
	public function __construct(
		private UserFolderHelper $userFolderHelper,
		private CollectiveHelper $collectiveHelper,
	) {
	}

	/**
	 * True if any of the given paths (relative to the user folder) equals,
	 * contains or is contained by a collective whose downloads are disabled.
	 *
	 * @param list<string> $pathsToCheck
	 */
	public function isDownloadBlocked(string $userId, array $pathsToCheck): bool {
		$disabledCollectivePaths = $this->getDownloadDisabledCollectivePaths($userId);
		if ($disabledCollectivePaths === []) {
			return false;
		}

		foreach ($pathsToCheck as $path) {
			$path = $this->normalizePath($path);
			foreach ($disabledCollectivePaths as $collectivePath) {
				if ($this->pathsOverlap($path, $collectivePath)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns the mount paths (relative to the user folder, with leading slash)
	 * of all collectives that have downloads disabled.
	 *
	 * @return list<string>
	 */
	private function getDownloadDisabledCollectivePaths(string $userId): array {
		$userFolderSetting = rtrim($this->userFolderHelper->getUserFolderSetting($userId), '/');

		$paths = [];
		$collectives = $this->collectiveHelper->getCollectivesForUser($userId, true, false);
		foreach ($collectives as $collective) {
			if ($collective->canDownload()) {
				continue;
			}

			$paths[] = $this->normalizePath($userFolderSetting . '/' . $collective->getName());
		}

		return $paths;
	}

	/**
	 * True if the two paths are equal or one is an ancestor of the other.
	 */
	private function pathsOverlap(string $a, string $b): bool {
		return $a === $b
			|| $this->isAncestorOrEqual($a, $b)
			|| $this->isAncestorOrEqual($b, $a);
	}

	/**
	 * True if $ancestor is equal to or contains $descendant.
	 * Both paths are expected to be normalized (leading slash, no trailing slash).
	 */
	private function isAncestorOrEqual(string $ancestor, string $descendant): bool {
		if ($ancestor === '/') {
			return true;
		}

		return $ancestor === $descendant
			|| str_starts_with($descendant, $ancestor . '/');
	}

	private function normalizePath(string $path): string {
		// Collapse duplicate slashes (e.g. when concatenating root paths) and
		// strip leading/trailing slashes, always keeping a single leading slash.
		$path = preg_replace('#/+#', '/', str_replace('\\', '/', $path));
		return '/' . trim($path, '/');
	}
}
