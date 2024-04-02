<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;

class CollectiveHelper {
	public function __construct(private CollectiveMapper $collectiveMapper, private CollectiveUserSettingsMapper $collectiveUserSettingsMapper, private CircleHelper $circleHelper) {
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectivesForUser(string $userId, bool $getLevel = true, bool $getUserSettings = true): array {
		$circles = $this->circleHelper->getCircles($userId);
		$cids = array_map(fn ($circle) => $circle->getSingleId(), $circles);
		$circles = array_combine($cids, $circles);
		/** @var Collective[] $collectives */
		$collectives = $this->collectiveMapper->findByCircleIds($cids);
		foreach ($collectives as $c) {
			$cid = $c->getCircleId();
			$circle = $circles[$cid];
			$c->setName($circle->getSanitizedName());
			$c->setLevel($getLevel ? $circle->getInitiator()->getLevel(): 0);
			if ($getUserSettings) {
				// TODO: merge queries for collective and user settings into one?
				$settings = $this->collectiveUserSettingsMapper->findByCollectiveAndUser($c->getId(), $userId);
				$c->setUserPageOrder(($settings ? $settings->getSetting('page_order') : null) ?? Collective::defaultPageOrder);
				$c->setUserShowRecentPages(($settings ? $settings->getSetting('show_recent_pages') : null) ?? Collective::defaultShowRecentPages);
			}
		}
		return $collectives;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectivesTrashForUser(string $userId): array {
		$circles = $this->circleHelper->getCircles($userId);
		$cids = array_map(fn ($circle) => $circle->getSingleId(), $circles);
		$circles = array_combine($cids, $circles);
		$collectives = $this->collectiveMapper->findTrashByCircleIdsAndUser($cids, $userId);
		foreach ($collectives as $c) {
			$cid = $c->getCircleId();
			$c->setName($circles[$cid]->getSanitizedName());
			$c->setLevel($this->circleHelper->getLevel($cid, $userId));
		}
		return $collectives;
	}
}
