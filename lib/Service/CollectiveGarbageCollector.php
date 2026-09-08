<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use Psr\Log\LoggerInterface;

class CollectiveGarbageCollector {
	public function __construct(
		private readonly CollectiveMapper $collectiveMapper,
		private readonly CircleHelper $circleHelper,
		private readonly CollectiveService $collectiveService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Delete collectives (including trashed ones) whose team no longer exists
	 */
	public function purgeOrphanedCollectives(): int {
		$purgeCount = 0;
		foreach ($this->collectiveMapper->getAll() as $collective) {
			try {
				if (!$this->isOrphaned($collective)) {
					continue;
				}
			} catch (MissingDependencyException $e) {
				$this->logger->debug('Skipping purge of orphaned collectives, teams app not available: ' . $e->getMessage());
				return $purgeCount;
			}

			try {
				$this->collectiveService->purgeCollective($collective);
			} catch (NotFoundException|NotPermittedException $e) {
				$this->logger->warning('Failed to purge orphaned collective ' . $collective->getId() . ': ' . $e->getMessage(), ['exception' => $e]);
				continue;
			}

			$this->logger->info('Purged orphaned collective ' . $collective->getId() . ', team ' . $collective->getCircleId() . ' does not exist anymore.');
			$purgeCount++;
		}

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
