<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\SessionService;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<Session> */
class SessionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_sessions', Session::class);
	}

	/**
	 * @throws NotFoundException
	 */
	public function find(int $collectiveId, string $userId, string $token): Session {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->andWhere($qb->expr()->gt('last_contact', $qb->createNamedParameter(time() - SessionService::SESSION_VALID_TIME)))
			->executeQuery();

		$data = $result->fetch();
		$result->closeCursor();
		if ($data === false) {
			throw new NotFoundException('Session is invalid');
		}
		$session = Session::fromRow($data);
		if ($session->getUserId() !== $userId || $session->getCollectiveId() !== $collectiveId) {
			throw new NotFoundException('Session is invalid');
		}
		return $session;
	}

	public function findAllActive(int $collectiveId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'user_id', 'collective_id', 'token', 'last_contact')

			->from($this->getTableName())
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->gt('last_contact', $qb->createNamedParameter(time() - SessionService::SESSION_VALID_TIME)))
			->executeQuery();

		return $this->findEntities($qb);
	}

	public function deleteInactive(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lt('last_contact', $qb->createNamedParameter(time() - SessionService::SESSION_VALID_TIME)));
		return $qb->executeStatement();
	}
}
