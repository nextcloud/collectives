<?php

namespace OCA\Collectives\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class ExpireCollectiveVersions extends TimedJob {
	public const ITEMS_PER_SESSION = 1000;

	/** @var CollectiveVersionsExpireManager */
	private $expireManager;

	public function __construct(CollectiveVersionsExpireManager $expireManager) {
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
