<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Listeners;

use OCA\Circles\Events\MembershipsRemovedEvent;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\CollectiveShareService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * Delete the share links of users that are no longer members of a collective
 *
 * Circles only reports a membership as removed once the user has no path into the team
 * anymore, neither directly nor via groups or other teams.
 *
 * @template-implements IEventListener<Event|MembershipsRemovedEvent>
 */
class MembershipsRemovedListener implements IEventListener {
	public function __construct(
		private readonly CollectiveMapper $collectiveMapper,
		private readonly CircleHelper $circleHelper,
		private readonly CollectiveShareService $shareService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof MembershipsRemovedEvent)) {
			return;
		}

		foreach ($event->getMemberships() as $membership) {
			try {
				$collective = $this->collectiveMapper->findByCircleId($membership->getCircleId(), true);
				if ($collective === null) {
					continue;
				}

				$userId = $this->circleHelper->getLocalUserId($membership->getSingleId());
				if ($userId === null) {
					continue;
				}

				$this->shareService->deleteSharesByCollectiveIdAndUser($collective->getId(), $userId);
			} catch (\Exception $e) {
				// Don't break the membership update in Circles
				$this->logger->warning('Failed to delete share links of former collective member', ['exception' => $e]);
			}
		}
	}
}
