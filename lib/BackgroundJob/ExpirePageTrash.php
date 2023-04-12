<?php

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Trash\PageTrashBackend;
use OCA\Files_Trashbin\Expiration;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

class ExpirePageTrash extends TimedJob {
	private Expiration $expiration;
	private PageTrashBackend $trashBackend;

	public function __construct(
		ITimeFactory $time,
		Expiration $expiration,
		PageTrashBackend $trashBackend) {
		parent::__construct($time);

		// Run once per hour
		$this->setInterval(60 * 60);

		$this->expiration = $expiration;
		$this->trashBackend = $trashBackend;
	}

	/**
	 * @param $argument
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function run($argument): void {
		$this->trashBackend->expire($this->expiration);
	}
}
