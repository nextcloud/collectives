<?php

namespace OCA\Collectives\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\Collectives\Db\PageGarbageCollector;

class PurgeObsoletePages extends TimedJob {
	public const ITEMS_PER_SESSION = 1000;

	/** @var PageGarbageCollector */
	private $garbageCollector;

	public function __construct(PageGarbageCollector $garbageCollector) {
		// Run once every two days
		$this->setInterval(60 * 60 * 24 * 2);

		$this->garbageCollector = $garbageCollector;
	}

	/**
	 * @param $argument
	 */
	protected function run($argument): void {
		$this->garbageCollector->purgeObsoletePages();
	}
}
