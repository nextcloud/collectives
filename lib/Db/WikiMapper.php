<?php

namespace OCA\Wiki\Db;

use OCA\Wiki\Db\Wiki;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Wiki insert(Wiki $wiki) : Wiki
 * @method Wiki delete(Wiki $wiki) : Wiki
 * @method Wiki findEntity(IQueryBuilder $query) : Wiki
 */
class WikiMapper extends QBMapper {
	public function __construct(
		IDBConnection $db) {
		parent::__construct($db, 'wiki', Wiki::Class);
	}

	/**
	 * @param string $circleUniqueId
	 *
	 * @return Wiki|null
	 */
	public function findByCircleId(string $circleUniqueId): ?Wiki {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId, IQueryBuilder::PARAM_STR))
			);
		try {
			return $this->findEntity($qb);
		} catch(DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @param int $id
	 *
	 * @return \OCA\Wiki\Db\Wiki|null
	 */
	public function findById(int $id): ?Wiki {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		try {
			return $this->findEntity($qb);
		} catch(DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}
}
