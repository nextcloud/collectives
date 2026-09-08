<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Service\CollectiveGarbageCollector;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

class PurgeOrphanedCollectives extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly CollectiveGarbageCollector $garbageCollector,
	) {
		parent::__construct($time);

		// Run once every two days
		$this->setInterval(60 * 60 * 24 * 2);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	/**
	 * @param $argument
	 */
	protected function run($argument): void {
		$this->garbageCollector->purgeOrphanedCollectives();
	}
}
