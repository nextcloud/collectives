<?php

namespace OCA\Collectives\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCP\AppFramework\QueryException;

class CollectiveHelper {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/**
	 * CollectiveHelper constructor.
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
