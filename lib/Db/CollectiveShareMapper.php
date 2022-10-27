<?php

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
 */
class CollectiveShareMapper extends QBMapper {
	/**
	 * CollectiveMapper constructor.
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_shares', CollectiveShare::class);
	}

	/**
	 * @param int $collectiveId
	 *
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
	 * @param int    $collectiveId
	 * @param string $userId
	 *
	 * @return CollectiveShare
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findOneByCollectiveIdAndUser(int $collectiveId, string $userId): CollectiveShare {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)));
		$where->add($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		return $this->findEntity($qb);
	}

	/**
	 * @param string $token
	 *
	 * @return CollectiveShare
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
	 * @param int    $collectiveId
	 * @param string $token
	 * @param string $userId
	 *
	 * @return CollectiveShare
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findOneByCollectiveIdAndTokenAndUser(int $collectiveId, string $token, string $userId): CollectiveShare {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)));
		$where->add($qb->expr()->eq('token', $qb->createNamedParameter($token, IQueryBuilder::PARAM_STR)));
		$where->add($qb->expr()->eq('owner', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		return $this->findEntity($qb);
	}

	/**
	 * @param int    $collectiveId
	 * @param string $token
	 * @param string $owner
	 *
	 * @return CollectiveShare
	 * @throws Exception
	 */
	public function create(int $collectiveId, string $token, string $owner): CollectiveShare {
		$share = new CollectiveShare();
		$share->setCollectiveId($collectiveId);
		$share->setToken($token);
		$share->setOwner($owner);

		return $this->insert($share);
	}

	/**
	 * @param string $token
	 *
	 * @return CollectiveShare
	 * @throws Exception
	 */
	public function deleteByToken(string $token): ?CollectiveShare {
		try {
			$share = $this->findOneByToken($token);
			return $this->delete($share);
		} catch (MultipleObjectsReturnedException | DoesNotExistException $e) {
		}

		return null;
	}
}
