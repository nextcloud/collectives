<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<CollectiveUserSettings>
 */
class CollectiveUserSettingsMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_u_settings', CollectiveUserSettings::class);
	}

	/**
	 * Insert new settings entity or update existing one
	 *
	 * Provide our own implementation of `insertOrUpdate` as the one from QBMapper throws
	 * `REASON_NOT_NULL_CONSTRAINT_VIOLATION` for existing entities.
	 *
	 * @throws Exception
	 */
	public function insertOrUpdate(CollectiveUserSettings|Entity $entity): CollectiveUserSettings {
		if ($entity->getId() === null) {
			return $this->insert($entity);
		}
		return $this->update($entity);
	}

	/**
	 * @return CollectiveUserSettings[]
	 * @throws Exception
	 */
	public function findByCollectiveId(int $collectiveId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByCollectiveAndUser(int $collectiveId, string $userId): ?CollectiveUserSettings {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
		];
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		try {
			/** @var CollectiveUserSettings $this->findEntity($qb) */
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function deleteByCollectiveId(int $collectiveId): void {
		$settings = $this->findByCollectiveId($collectiveId);
		foreach ($settings as $s) {
			$this->delete($s);
		}
	}
}
