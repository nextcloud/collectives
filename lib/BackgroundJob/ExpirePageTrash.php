<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Trash\PageTrashBackend;
use OCA\Files_Trashbin\Expiration;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Server;

class ExpirePageTrash extends TimedJob {
	private Expiration $expiration;
	private PageTrashBackend $trashBackend;
	private bool $hasTrashBackend = false;

	public function __construct(ITimeFactory $time) {
		parent::__construct($time);

		// Run once per hour
		$this->setInterval(60 * 60);

		if (class_exists(Expiration::class)) {
			$this->hasTrashBackend = true;
			$this->expiration = Server::get(Expiration::class);
			$this->trashBackend = Server::get(PageTrashBackend::class);
		}
	}

	/**
	 * @param $argument
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function run($argument): void {
		if ($this->hasTrashBackend) {
			$this->trashBackend->expire($this->expiration);
		}
	}
}
