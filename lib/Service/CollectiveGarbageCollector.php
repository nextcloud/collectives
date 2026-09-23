<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

class CollectiveGarbageCollector {
	private const CONFIG_KEY = 'orphaned_collectives';
	// Collectives need to be orphaned for at least a week before they get purged
	public const GRACE_PERIOD = 60 * 60 * 24 * 7;

	public function __construct(
		private readonly CollectiveMapper $collectiveMapper,
		private readonly CircleHelper $circleHelper,
		private readonly CollectiveService $collectiveService,
		private readonly IAppConfig $appConfig,
		private readonly ITimeFactory $timeFactory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Delete collectives (including trashed ones) whose team no longer exists
	 *
	 * Collectives are first only marked as orphaned. They get purged once they
	 * have been orphaned for longer than the grace period. This protects against
	 * teams being temporarily unavailable, e.g. during upgrades.
	 */
	public function purgeOrphanedCollectives(): int {
		$collectives = $this->collectiveMapper->getAll();

		$orphaned = [];
		foreach ($collectives as $collective) {
			try {
				if ($this->isOrphaned($collective)) {
					$orphaned[] = $collective;
				}
			} catch (MissingDependencyException $e) {
				$this->logger->debug('Skipping purge of orphaned collectives, teams app not available: ' . $e->getMessage());
				return 0;
			}
		}

		if (count($collectives) > 1 && count($orphaned) === count($collectives)) {
			$this->logger->warning('Skipping purge of orphaned collectives: team of all ' . count($collectives) . ' collectives not found. Something seems odd');
			return 0;
		}

		/** @var array<string, int> $previouslyOrphaned */
		$previouslyOrphaned = $this->appConfig->getValueArray('collectives', self::CONFIG_KEY, lazy: true);
		$now = $this->timeFactory->getTime();
		$stillOrphaned = [];
		$purgeCount = 0;
		foreach ($orphaned as $collective) {
			$id = (string)$collective->getId();
			$orphanedSince = (int)($previouslyOrphaned[$id] ?? $now);
			if ($orphanedSince === $now) {
				$this->logger->warning('Team ' . $collective->getCircleId() . ' of collective ' . $id . ' not found, marking collective as orphaned. It will be purged after ' . intdiv(self::GRACE_PERIOD, 86400) . ' days.');
			}

			if ($now - $orphanedSince < self::GRACE_PERIOD) {
				$stillOrphaned[$id] = $orphanedSince;
				continue;
			}

			$this->logger->warning('Purging orphaned collective ' . $collective->getId() . ', team ' . $collective->getCircleId() . ' does not exist since ' . date('c', $orphanedSince) . '.');
			try {
				$this->collectiveService->purgeCollective($collective);
			} catch (NotFoundException|NotPermittedException $e) {
				$this->logger->warning('Failed to purge orphaned collective ' . $collective->getId() . ': ' . $e->getMessage(), ['exception' => $e]);
				continue;
			}
			$purgeCount++;
		}

		// Only keep collectives that were orphaned in this run
		$this->appConfig->setValueArray('collectives', self::CONFIG_KEY, $stillOrphaned, lazy: true);

		return $purgeCount;
	}

	/**
	 * Only a definite "team not found" counts as orphaned. Any other error
	 * (e.g. database or request errors) must never lead to deletion.
	 *
	 * @throws MissingDependencyException
	 */
	private function isOrphaned(Collective $collective): bool {
		try {
			$this->circleHelper->getCircle($collective->getCircleId(), null, true);
		} catch (NotFoundException) {
			return true;
		} catch (NotPermittedException $e) {
			$this->logger->warning('Failed to check team of collective ' . $collective->getId() . ': ' . $e->getMessage(), ['exception' => $e]);
		}

		return false;
	}
}
