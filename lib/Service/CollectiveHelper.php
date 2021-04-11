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
		$joinedCircleIds = Circles::joinedCircleIds($userId);
		foreach ($joinedCircleIds as $cId) {
			if (null !== $c = $this->collectiveMapper->findByCircleId($cId)) {
				$admin = $getAdmin && $this->collectiveMapper->isAdmin($c, $userId);
				$collectiveInfos[] = new CollectiveInfo($c, $admin);
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
		$joinedCircleIds = Circles::joinedCircleIds($userId);
		foreach ($joinedCircleIds as $cId) {
			if ((null !== $c = $this->collectiveMapper->findByCircleId($cId, $userId, true))) {
				$collectiveInfos[] = new CollectiveInfo($c, true);
			}
		}
		return $collectiveInfos;
	}
}
