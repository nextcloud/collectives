<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Wrapper\CacheJail;
use OC\Files\Storage\Wrapper\Jail;
use OCP\Files\Cache\ICache;

class CollectiveEncryptionJail extends Jail {
	public function getCache($path = '', $storage = null): ICache {
		if (!$storage) {
			$storage = $this->getWrapperStorage();
		}

		// Per default the jail reuses the inner cache, but when encryption is
		// enabled the storage needs to be passed to the cache so it takes into
		// account the outer Encryption wrapper.
		$sourceCache = $this->getWrapperStorage()->getCache($this->getUnjailedPath($path), $storage);

		return new CacheJail($sourceCache, $this->rootPath);
	}
}
