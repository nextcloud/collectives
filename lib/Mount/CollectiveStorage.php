<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Scanner;
use OC\Files\ObjectStore\ObjectStoreScanner;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IScanner;
use OCP\Files\Storage\IConstructableStorage;
use OCP\Files\Storage\IStorage;
use OCP\IUser;

class CollectiveStorage extends Wrapper implements IConstructableStorage {
	private readonly int $folderId;
	private readonly ?ICacheEntry $rootEntry;
	private readonly ?IUser $mountOwner;

	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->folderId = $parameters['folder_id'];
		$this->rootEntry = $parameters['rootCacheEntry'];
		$this->mountOwner = $parameters['mountOwner'];
	}

	public function getFolderId(): int {
		return $this->folderId;
	}

	public function getOwner(string $path): string|false {
		return $this->mountOwner !== null ? $this->mountOwner->getUID() : false;
	}

	public function getCache(string $path = '', ?IStorage $storage = null): ICache {
		if ($this->cache) {
			return $this->cache;
		}
		if (!$storage) {
			$storage = $this;
		}

		$cache = parent::getCache($path, $storage);
		if ($this->rootEntry !== null) {
			$cache = new RootEntryCache($cache, $this->rootEntry);
		}
		$this->cache = $cache;

		return $this->cache;
	}

	public function getScanner(string $path = '', ?IStorage $storage = null): IScanner {
		if (!$storage) {
			$storage = $this;
		}
		if ($storage->instanceOfStorage(ObjectStoreStorage::class)) {
			$storage->scanner = new ObjectStoreScanner($storage);
		} elseif (!isset($storage->scanner)) {
			$storage->scanner = new Scanner($storage);
		}
		return $storage->scanner;
	}

	public function touch(string $path, ?int $mtime = null): bool {
		// Make sure the collective root directory exists on disk before writing.
		// The physical directory can be missing while a filecache entry still exists.
		$this->mkdir('');
		return parent::touch($path, $mtime);
	}

	public function file_put_contents(string $path, mixed $data): int|float|false {
		$this->mkdir('');
		return parent::file_put_contents($path, $data);
	}
}
