<?php

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Model\CollectiveInfo;

class CollectiveHelper {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var CircleHelper */
	private $circleHelper;

	/**
	 * CollectiveHelper constructor.
	 *
	 * @param CollectiveMapper $collectiveMapper
	 * @param CircleHelper     $circleHelper
	 */
	public function __construct(CollectiveMapper $collectiveMapper,
								CircleHelper $circleHelper) {
		$this->collectiveMapper = $collectiveMapper;
		$this->circleHelper = $circleHelper;
	}

	/**
	 * @param string $userId
	 * @param bool   $getAdmin
	 *
	 * @return CollectiveInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectivesForUser(string $userId, bool $getAdmin = true): array {
		$collectiveInfos = [];
		$circles = $this->circleHelper->getCircles($userId);
		foreach ($circles as $circle) {
			$cid = $circle->getUniqueId();
			if (null !== $c = $this->collectiveMapper->findByCircleId($cid)) {
				$admin = $getAdmin && $this->circleHelper->isAdmin($c->getCircleUniqueId(), $userId);
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
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectivesTrashForUser(string $userId): array {
		$collectiveInfos = [];
		$circles = $this->circleHelper->getCircles($userId);
		foreach ($circles as $circle) {
			$cid = $circle->getUniqueId();
			if ((null !== $c = $this->collectiveMapper->findTrashByCircleId($cid, $userId))) {
				$collectiveInfos[] = new CollectiveInfo($c,
					$this->collectiveMapper->circleIdToName($c->getCircleUniqueId()),
					true);
			}
		}
		return $collectiveInfos;
	}
}
