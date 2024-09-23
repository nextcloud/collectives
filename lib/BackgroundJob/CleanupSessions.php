<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Service\SessionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class CleanupSessions extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private SessionService $sessionService,
	) {
		parent::__construct($time);

		$this->setInterval(SessionService::SESSION_VALID_TIME);
	}

	protected function run($argument): void {
		$this->sessionService->removeInactiveSessions();
	}
}
