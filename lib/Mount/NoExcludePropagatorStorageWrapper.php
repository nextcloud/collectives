<?php

declare(strict_types=1);

namespace OCA\Collectives\Mount;

use OC;
use OC\Files\Cache\Propagator;
use OC\Files\Storage\Storage;
use OC\Files\Storage\Wrapper\Wrapper;

class NoExcludePropagatorStorageWrapper extends Wrapper {
	/**
	 * get a propagator instance for the cache
	 *
	 * The default storage adds `appdata_<instanceid>` to the ignore list.
	 * Our collective folders live there, so we need our own propagator.
	 */
	public function getPropagator($storage = null): Propagator {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($storage->propagator)) {
			$storage->propagator = new Propagator($storage, OC::$server->getDatabaseConnection());
		}
		return $storage->propagator;
	}
}
