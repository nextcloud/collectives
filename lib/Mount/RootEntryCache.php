<?php

declare(strict_types=1);

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Wrapper\CacheWrapper;
use OCP\Files\Cache\ICache;
use OCP\Files\Cache\ICacheEntry;

class RootEntryCache extends CacheWrapper {
	private ?ICacheEntry $rootEntry;

	/**
	 * RootEntryCache constructor.
	 *
	 * @param ICache           $cache
	 * @param ICacheEntry|null $rootEntry
	 */
	public function __construct(ICache $cache,
								ICacheEntry $rootEntry = null) {
		parent::__construct($cache);
		$this->rootEntry = $rootEntry;
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
	 *
	 * @return int
	 */
	public function getId($file): int {
		if ($file === '' && $this->rootEntry) {
			return $this->rootEntry->getId();
		}
		return parent::getId($file);
	}

	/**
	 * @param int   $id
	 * @param array $data
	 */
	public function update($id, array $data): void {
		$this->rootEntry = null;
		parent::update($id, $data);
	}

	/**
	 * @param string $file
	 * @param array  $data
	 *
	 * @return int
	 */
	public function insert($file, array $data): int {
		$this->rootEntry = null;
		return parent::insert($file, $data);
	}
}
