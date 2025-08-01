<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\Session;
use OCA\Collectives\Db\SessionMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Security\ISecureRandom;

class SessionService {
	public const SESSION_VALID_TIME = 120;

	public function __construct(
		private CollectiveMapper $collectiveMapper,
		private SessionMapper $sessionMapper,
		private ISecureRandom $secureRandom,
		private ITimeFactory $timeFactory,
	) {
	}

	private function checkPermissions(int $collectiveId, string $userId): void {
		if ($this->collectiveMapper->findByIdAndUser($collectiveId, $userId) === null) {
			throw new NotFoundException('Collective not found: ' . $collectiveId);
		}
	}

	/**
	 * @throws NotFoundException
	 */
	public function initSession(int $collectiveId, string $userId): Session {
		$this->checkPermissions($collectiveId, $userId);
		$session = new Session();
		$session->setUserId($userId);
		$session->setToken($this->secureRandom->generate(32));
		$session->setCollectiveId($collectiveId);
		$session->setLastContact($this->timeFactory->getTime());
		return $this->sessionMapper->insert($session);
	}

	/**
	 * @throws NotFoundException
	 */
	public function syncSession(int $collectiveId, string $token, string $userId): void {
		$session = $this->sessionMapper->find($collectiveId, $userId, $token);
		$session->setLastContact($this->timeFactory->getTime());
		$this->sessionMapper->update($session);
	}

	public function closeSession(int $collectiveId, string $token, string $userId): void {
		try {
			$session = $this->sessionMapper->find($collectiveId, $userId, $token);
			$this->sessionMapper->delete($session);
		} catch (NotFoundException) {
		}
	}

	public function removeInactiveSessions(): int {
		return $this->sessionMapper->deleteInactive();
	}

	public function getSessionUsers(int $collectiveId, ?string $sessionToken = null): array {
		$activeSessions = $this->sessionMapper->findAllActive($collectiveId);
		$userIds = [];
		foreach ($activeSessions as $session) {
			if ($sessionToken && $session->getToken() === $sessionToken) {
				// Limitation:
				// If same user has a second active session, $sessionToken still gets notified
				// because notify_push notifies users, not individual sessions
				// https://github.com/nextcloud/notify_push/issues/195
				continue;
			}

			// Don't notify same user multiple times
			$userIds[(string)$session->getUserId()] = 1;
		}

		return array_keys($userIds);
	}
}
