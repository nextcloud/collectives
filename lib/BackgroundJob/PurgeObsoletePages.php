<?php

namespace OCA\Collectives\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Collectives\Db\PageGarbageCollector;

class PurgeObsoletePages extends TimedJob {
	/** @var PageGarbageCollector */
	private $garbageCollector;

	public function __construct(ITimeFactory $time,
								PageGarbageCollector $garbageCollector) {
		parent::__construct($time);

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
