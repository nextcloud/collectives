<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
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
 * @method CollectiveShare insert(Entity $share)
 * @method CollectiveShare delete(Entity $share)
 * @method CollectiveShare findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<CollectiveShare>
 */
class CollectiveShareMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_shares', CollectiveShare::class);
	}

	/**
	 * @return CollectiveShare[]
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
	 * @return CollectiveShare[]
	 * @throws Exception
	 */
	public function findByCollectiveIdAndUser(int $collectiveId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
		];
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findOneByCollectiveIdAndUser(int $collectiveId, int $pageId, string $userId): CollectiveShare {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('page_id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
		];
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findOneByToken(string $token): CollectiveShare {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findOneByCollectiveIdAndTokenAndUser(int $collectiveId, int $pageId, string $token, string $userId): CollectiveShare {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('page_id', $qb->createNamedParameter($pageId, IQueryBuilder::PARAM_INT)),
			$qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)),
			$qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
		];
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		return $this->findEntity($qb);
	}

	/**
	 * @throws Exception
	 */
	public function create(int $collectiveId, int $pageId, string $token, string $owner): CollectiveShare {
		$share = new CollectiveShare();
		$share->setCollectiveId($collectiveId);
		$share->setPageId($pageId);
		$share->setToken($token);
		$share->setOwner($owner);

		return $this->insert($share);
	}

	/**
	 * @throws Exception
	 */
	public function deleteByToken(string $token): ?CollectiveShare {
		try {
			$share = $this->findOneByToken($token);
			return $this->delete($share);
		} catch (MultipleObjectsReturnedException|DoesNotExistException) {
		}

		return null;
	}
}
