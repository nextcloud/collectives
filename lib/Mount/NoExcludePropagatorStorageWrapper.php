<?php

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Propagator;
use OC\Files\Storage\Storage;
use OC\Files\Storage\Wrapper\Wrapper;

class NoExcludePropagatorStorageWrapper extends Wrapper {
	/** @var Storage */
	protected $storage = null;

	/**
	 * get a propagator instance for the cache
	 *
	 * The default storage adds `appdata_<instanceid>` to the ignore list.
	 * Our collective folders live there, so we need our own propagator.
	 *
	 * @param Storage|null $storage (optional) the storage to pass to the watcher
	 *
	 * @return Propagator
	 */
	public function getPropagator($storage = null): Propagator {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($storage->propagator)) {
			$storage->propagator = new Propagator($storage, \OC::$server->getDatabaseConnection());
		}
		return $storage->propagator;
	}
}
