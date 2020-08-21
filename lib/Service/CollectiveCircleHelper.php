<?php

namespace OCA\Unite\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Unite\Db\Collective;
use OCA\Unite\Db\CollectiveMapper;
use OCP\AppFramework\QueryException;

class CollectiveCircleHelper {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/**
	 * CollectiveCircleHelper constructor.
	 *
	 * @param CollectiveMapper $collectiveMapper
	 */
	public function __construct(CollectiveMapper $collectiveMapper) {
		$this->collectiveMapper = $collectiveMapper;
	}

	/**
	 * @param string $userId
	 *
	 * @return Collective[]
	 * @throws QueryException
	 */
	public function getCollectivesForUser(string $userId): array {
		$collectives = [];
		$joinedCircles = Circles::joinedCircles($userId);
		foreach ($joinedCircles as $jc) {
			if (null !== $c = $this->collectiveMapper->findByCircleId($jc->getUniqueId())) {
				$collectives[] = $c;
			}
		}
		return $collectives;
	}
}
