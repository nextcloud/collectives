<?php

declare(strict_types=1);

namespace OCA\Collectives\Events;

use OCA\Collectives\Db\Collective;
use OCP\EventDispatcher\Event;

class CollectiveDeletedEvent extends Event {
	/** @var Collective */
	private $collective;

	public function __construct(Collective $collective) {
		parent::__construct();
		$this->collective = $collective;
	}

	/**
	 * @return Collective
	 */
	public function getCollective(): Collective {
		return $this->collective;
	}
}
