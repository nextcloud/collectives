<?php

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Collectives\Db\CollectiveGarbageCollector;

class PurgeObsoleteCollectives extends TimedJob {
	/** @var CollectiveGarbageCollector */
	private $garbageCollector;

	public function __construct(ITimeFactory $time,
								CollectiveGarbageCollector $garbageCollector) {
		parent::__construct($time);

		// Run once every two days
		$this->setInterval(60 * 60 * 24 * 2);

		$this->garbageCollector = $garbageCollector;
	}

	/**
	 * @param $argument
	 *
	 * @throws NotPermittedException
	 */
	protected function run($argument): void {
		$this->garbageCollector->purgeObsoleteCollectives();
	}
}
