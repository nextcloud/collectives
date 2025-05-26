<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Scanner;
use OC\Files\ObjectStore\NoopScanner;
use OC\Files\ObjectStore\ObjectStoreScanner;
use OC\Files\ObjectStore\ObjectStoreStorage;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Cache\IScanner;
use OCP\Files\Storage\IStorage;
use OCP\IUser;

class CollectiveStorage extends Wrapper {
	private int $folderId;
	private ICacheEntry $rootEntry;
	private ?IUser $mountOwner;
	/** @var RootEntryCache */
	public $cache;

	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->folderId = $parameters['folder_id'];
		$this->rootEntry = $parameters['rootCacheEntry'];
		$this->mountOwner = $parameters['mountOwner'];
	}

	public function getFolderId(): int {
		return $this->folderId;
	}

	/**
	 * @param string $path
	 *
	 * @return string|false
	 */
	public function getOwner($path): string|false {
		return $this->mountOwner !== null ? $this->mountOwner->getUID() : false;
	}

	/**
	 * @param string $path
	 * @param IStorage|null $storage
	 */
	public function getCache($path = '', $storage = null): RootEntryCache {
		if ($this->cache) {
			return $this->cache;
		}
		if (!$storage) {
			$storage = $this;
		}

		$this->cache = new RootEntryCache(parent::getCache($path, $storage), $this->rootEntry);
		return $this->cache;
	}

	/**
	 * @param string $path
	 * @param null $storage
	 */
	public function getScanner($path = '', $storage = null): IScanner {
		if (!$storage) {
			$storage = $this;
		}
		if ($storage->instanceOfStorage(ObjectStoreStorage::class)) {
			// NoopScanner is private API and kept here for compatibility with older releases
			$storage->scanner = class_exists(NoopScanner::class) ? new NoopScanner($storage) : new ObjectStoreScanner($storage);
		} elseif (!isset($storage->scanner)) {
			$storage->scanner = new Scanner($storage);
		}
		return $storage->scanner;
	}
}
