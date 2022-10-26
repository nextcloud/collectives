<?php

namespace OCA\Collectives\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Collectives\Db\PageGarbageCollector;
use function method_exists;

class PurgeObsoletePages extends TimedJob {
	private PageGarbageCollector $garbageCollector;

	public function __construct(ITimeFactory $time,
								PageGarbageCollector $garbageCollector) {
		parent::__construct($time);

		// Run once every two days
		$this->setInterval(60 * 60 * 24 * 2);
		// TODO: remove check with NC 24+
		if (method_exists($this, 'setTimeSensitivity')) {
			$this->setTimeSensitivity(self::TIME_INSENSITIVE);
		}

		$this->garbageCollector = $garbageCollector;
	}

	/**
	 * @param $argument
	 */
	protected function run($argument): void {
		$this->garbageCollector->purgeObsoletePages();
	}
}
