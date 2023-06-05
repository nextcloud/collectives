<?php

declare(strict_types=1);

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
	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_u_settings', CollectiveUserSettings::class);
	}

	/**
	 * Insert new settings entity or update existing one
	 *
	 * Provide our own implementation of `insertOrUpdate` as the one from QBMapper throws
	 * `REASON_NOT_NULL_CONSTRAINT_VIOLATION` for existing entities.
	 *
	 * TODO: Migrate to using `CollectiveUserSettings` in type hints once we drop PHP7.3.
	 *
	 * @param Entity $entity
	 *
	 * @return CollectiveUserSettings
	 * @throws Exception
	 */
	public function insertOrUpdate(Entity $entity): Entity {
		if ($entity->getId() === null) {
			return $this->insert($entity);
		}
		return $this->update($entity);
	}

	/**
	 * @param int $collectiveId
	 *
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
	 * @param int    $collectiveId
	 * @param string $userId
	 *
	 * @return CollectiveUserSettings|null
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function findByCollectiveAndUser(int $collectiveId, string $userId): ?CollectiveUserSettings {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)));
		$where->add($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * @param int    $collectiveId
	 * @param string $userId
	 *
	 * @return int|null
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getPageOrder(int $collectiveId, string $userId): ?int {
		$settings = $this->findByCollectiveAndUser($collectiveId, $userId);
		return $settings ? $settings->getPageOrder() : null;
	}

	public function deleteByCollectiveId(int $collectiveId): void {
		$settings = $this->findByCollectiveId($collectiveId);
		foreach ($settings as $s) {
			$this->delete($s);
		}
	}
}
