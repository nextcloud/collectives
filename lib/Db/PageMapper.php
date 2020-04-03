<?php

namespace OCA\Wiki\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PageMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'wiki', Page::class);
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Entity|Page
	 */
	public function find(int $id, string $userId): Entity {
		/** @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $this->findEntity($qb);
	}

	/**
	 * @param string $userId
	 *
	 * @return array|Entity[]
	 */
	public function findAll(string $userId): array {
		/** @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		return $this->findEntities($qb);
	}
}
