<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Wrapper\CacheWrapper;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;

class RootEntryCache extends CacheWrapper {
	public function __construct(
		ICache $cache,
		private ?ICacheEntry $rootEntry,
	) {
		parent::__construct($cache);
	}

	/**
	 * @param int|string $file
	 *
	 * @return ICacheEntry|false
	 */
	public function get($file) {
		if ($file === '' && $this->rootEntry) {
			return $this->rootEntry;
		}
		return parent::get($file);
	}

	/**
	 * @param string $file
	 */
	public function insert($file, array $data): int {
		$this->rootEntry = null;
		return parent::insert($file, $data);
	}

	/**
	 * @param int $id
	 */
	public function update($id, array $data): void {
		$this->rootEntry = null;
		parent::update($id, $data);
	}

	/**
	 * @param string $file
	 */
	public function getId($file): int {
		if ($file === '' && $this->rootEntry) {
			return $this->rootEntry->getId();
		}
		return parent::getId($file);
	}
}
