<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class ExpirePageVersions extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private CollectiveVersionsExpireManager $expireManager,
	) {
		parent::__construct($time);

		// Run once per hour
		$this->setInterval(60 * 60);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
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
