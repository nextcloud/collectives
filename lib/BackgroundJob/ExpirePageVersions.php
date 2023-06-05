<?php

declare(strict_types=1);

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class ExpirePageVersions extends TimedJob {
	private CollectiveVersionsExpireManager $expireManager;

	public function __construct(ITimeFactory $time,
		CollectiveVersionsExpireManager $expireManager) {
		parent::__construct($time);

		// Run once per hour
		$this->setInterval(60 * 60);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);

		$this->expireManager = $expireManager;
	}

	/**
	 * @param $argument
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function run($argument): void {
		$this->expireManager->expireAll();
	}
}
