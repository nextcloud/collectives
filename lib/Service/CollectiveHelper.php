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
		$adminCircles = [];
		if ($getAdmin) {
			// For now only circle owners are allowed to delete the collective
			// $adminCircles = Circles::listCircles(Circles::CIRCLES_ALL, '', Circles::LEVEL_ADMIN, $userId);
			$adminCircles = Circles::listCircles(Circles::CIRCLES_ALL, '', Circles::LEVEL_OWNER, $userId);
		}
		foreach ($joinedCircleIds as $cId) {
			if (null !== $c = $this->collectiveMapper->findByCircleId($cId)) {
				$ci = new CollectiveInfo($c);
				foreach ($adminCircles as $ac) {
					if ($ac->getUniqueId() === $cId) {
						$ci->setAdmin(true);
					}
				}
				$collectiveInfos[] = $ci;
			}
		}
		return $collectiveInfos;
	}
}
