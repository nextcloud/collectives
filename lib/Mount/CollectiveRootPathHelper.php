<?php

namespace OCA\Collectives\Mount;

use OC\SystemConfig;

class CollectiveRootPathHelper {
	/** @var SystemConfig */
	private $systemConfig;

	/**
	 * CollectiveRootPathHelper constructor.
	 *
	 * @param SystemConfig $systemConfig
	 */
	public function __construct(SystemConfig $systemConfig) {
		$this->systemConfig = $systemConfig;
	}

	public function get(): string {
		$instanceId = $this->systemConfig->getValue('instanceid', null);
		if (null === $instanceId) {
			throw new \RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId . '/collectives';
	}
}
