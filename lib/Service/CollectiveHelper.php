<?php

namespace OCA\Collectives\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Model\CollectiveInfo;
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
	 * @param bool   $getAdmin
	 *
	 * @return CollectiveInfo[]
	 * @throws QueryException
	 */
	public function getCollectivesForUser(string $userId, bool $getAdmin = true): array {
		$collectiveInfos = [];
		$joinedCircles = Circles::joinedCircles($userId);
		foreach ($joinedCircles as $circle) {
			$id = $circle->getUniqueId();
			if (null !== $c = $this->collectiveMapper->findByCircleId($id)) {
				$admin = $getAdmin && $this->collectiveMapper->isAdmin($c, $userId);
				$collectiveInfos[] = new CollectiveInfo($c,
					$circle->getName(),
					$admin);
			}
		}
		return $collectiveInfos;
	}

	/**
	 * @param string $userId
	 *
	 * @return CollectiveInfo[]
	 * @throws QueryException
	 */
	public function getCollectivesTrashForUser(string $userId): array {
		$collectiveInfos = [];
		$joinedCircles = Circles::joinedCircles($userId);
		foreach ($joinedCircles as $circle) {
			$id = $circle->getUniqueId();
			if ((null !== $c = $this->collectiveMapper->findTrashByCircleId($id, $userId))) {
				$collectiveInfos[] = new CollectiveInfo($c,
					$this->collectiveMapper->circleUniqueIdToName($c->getCircleUniqueId()),
					true);
			}
		}
		return $collectiveInfos;
	}
}
