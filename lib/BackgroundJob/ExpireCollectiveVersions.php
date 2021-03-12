<?php

namespace OCA\Collectives\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class ExpireCollectiveVersions extends TimedJob {
	/** @var CollectiveVersionsExpireManager */
	private $expireManager;

	public function __construct(ITimeFactory $time,
								CollectiveVersionsExpireManager $expireManager) {
		parent::__construct($time);

		// Run once per hour
		$this->setInterval(60 * 60);

		$this->expireManager = $expireManager;
	}

	/**
	 * @param $argument
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function run($argument): void {
		$this->expireManager->expireAll();
	}
}
