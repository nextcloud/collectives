<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCA\Collectives\Model\CollectiveInfo;

class CollectiveHelper {
	private CollectiveMapper $collectiveMapper;
	private CollectiveUserSettingsMapper $collectiveUserSettingsMapper;
	private CircleHelper $circleHelper;

	/**
	 * CollectiveHelper constructor.
	 *
	 * @param CollectiveMapper             $collectiveMapper
	 * @param CollectiveUserSettingsMapper $collectiveUserSettingsMapper
	 * @param CircleHelper                 $circleHelper
	 */
	public function __construct(CollectiveMapper $collectiveMapper,
		CollectiveUserSettingsMapper $collectiveUserSettingsMapper,
		CircleHelper $circleHelper) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveUserSettingsMapper = $collectiveUserSettingsMapper;
		$this->circleHelper = $circleHelper;
	}

	/**
	 * @param string $userId
	 * @param bool   $getLevel
	 * @param bool   $getUserSettings
	 *
	 * @return CollectiveInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectivesForUser(string $userId, bool $getLevel = true, bool $getUserSettings = true): array {
		$collectiveInfos = [];
		$circles = $this->circleHelper->getCircles($userId);
		$cids = array_map(function ($circle) {
			return $circle->getSingleId();
		}, $circles);
		$circles = array_combine($cids, $circles);
		/** @var Collective[] $collectives */
		$collectives = $this->collectiveMapper->findByCircleIds($cids);
		foreach ($collectives as $c) {
			$cid = $c->getCircleId();
			$circle = $circles[$cid];
			$level = $getLevel ? $circle->getInitiator()->getLevel(): 0;
			$userPageOrder = null;
			$userShowRecentPages = null;
			if ($getUserSettings) {
				// TODO: merge queries for collective and user settings into one?
				$settings = $this->collectiveUserSettingsMapper->findByCollectiveAndUser($c->getId(), $userId);
				$userPageOrder = ($settings ? $settings->getSetting('page_order') : null) ?? Collective::defaultPageOrder;
				$userShowRecentPages = ($settings ? $settings->getSetting('show_recent_pages') : null) ?? Collective::defaultShowRecentPages;
			}
			$collectiveInfos[] = new CollectiveInfo(
				$c,
				$circle->getSanitizedName(),
				$level,
				null,
				false,
				false,
				$userPageOrder,
				$userShowRecentPages);
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
		$cids = array_map(function ($circle) {
			return $circle->getSingleId();
		}, $circles);
		$circles = array_combine($cids, $circles);
		$collectives = $this->collectiveMapper->findTrashByCircleIdsAndUser($cids, $userId);
		foreach ($collectives as $c) {
			$cid = $c->getCircleId();
			$collectiveInfos[] = new CollectiveInfo(
				$c,
				$circles[$cid]->getSanitizedName(),
				$this->circleHelper->getLevel($cid, $userId)
			);
		}
		return $collectiveInfos;
	}
}
