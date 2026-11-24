<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Service\StaticSiteRemover;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/**
 * Retries deleting websites of purged collectives at the static site generator, e.g. if it
 * wasn't available when the collective got purged.
 */
class DeleteOrphanedStaticSites extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly StaticSiteRemover $staticSiteRemover,
	) {
		parent::__construct($time);

		// Run once every six hours
		$this->setInterval(60 * 60 * 6);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	/**
	 * @param $argument
	 */
	protected function run($argument): void {
		$this->staticSiteRemover->removeAllOrphaned($this->time->getTime());
	}
}
