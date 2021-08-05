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
	 * @param bool   $getLevel
	 *
	 * @return CollectiveInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectivesForUser(string $userId, bool $getLevel = true): array {
		$collectiveInfos = [];
		$circles = $this->circleHelper->getCircles($userId);
		foreach ($circles as $circle) {
			$cid = $circle->getUniqueId();
			if (null !== $c = $this->collectiveMapper->findByCircleId($cid)) {
				$level = $getLevel ? $this->circleHelper->getLevel($c->getCircleId(), $userId): 0;
				$collectiveInfos[] = new CollectiveInfo($c,
					$circle->getSanitizedName(),
					$level);
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
	 * @throws MissingDependencyException
	 */
	public function getCollectivesTrashForUser(string $userId): array {
		$collectiveInfos = [];
		$circles = $this->circleHelper->getCircles($userId);
		foreach ($circles as $circle) {
			$cid = $circle->getUniqueId();
			if ((null !== $c = $this->collectiveMapper->findTrashByCircleId($cid, $userId))) {
				$collectiveInfos[] = new CollectiveInfo($c,
					$this->collectiveMapper->circleIdToName($c->getCircleId()),
					$this->circleHelper->getLevel($cid, $userId));
			}
		}
		return $collectiveInfos;
	}
}
