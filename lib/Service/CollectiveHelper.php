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
		$cids = array_map(function($circle) { return $circle->getUniqueId(); }, $circles);
		$circles = array_combine($cids, $circles);
		$collectives = $this->collectiveMapper->findByCircleIds($cids);
		foreach ($collectives as $c) {
			$cid = $c->getCircleId();
			$level = $getLevel ? $this->circleHelper->getLevel($cid, $userId): 0;
			$collectiveInfos[] = new CollectiveInfo($c,
				$circles[$cid]->getSanitizedName(),
				$level);
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
			$cid = $circle->getSingleId();
			if ((null !== $c = $this->collectiveMapper->findTrashByCircleIdAndUser($cid, $userId))) {
				$collectiveInfos[] = new CollectiveInfo($c,
					$this->collectiveMapper->circleIdToName($c->getCircleId(), $userId),
					$this->circleHelper->getLevel($cid, $userId));
			}
		}
		return $collectiveInfos;
	}
}
