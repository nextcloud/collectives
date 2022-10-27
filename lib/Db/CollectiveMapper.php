<?php

namespace OCA\Collectives\Db;

use OCA\Circles\Model\Member;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Collective insert(Entity $collective)
 * @method Collective delete(Entity $collective)
 * @method Collective findEntity(IQueryBuilder $query)
 * @method Collective update(Entity $collective)
 */
class CollectiveMapper extends QBMapper {
	private CircleHelper $circleHelper;

	/**
	 * CollectiveMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param CircleHelper  $circleHelper
	 */
	public function __construct(IDBConnection $db, CircleHelper $circleHelper) {
		$this->circleHelper = $circleHelper;
		parent::__construct($db, 'collectives', Collective::class);
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param string|null   $userId
	 * @param int           $level
	 *
	 * @return Collective|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	private function findBy(IQueryBuilder $qb, ?string $userId = null, int $level = Member::LEVEL_MEMBER): ?Collective {
		try {
			$collective = $this->findEntity($qb);
			// Return any found collective if $userId is null
			if (null === $userId) {
				return $collective;
			}
			// Return member collectives with at least level `level`
			return ($this->circleHelper->hasLevel($collective->getCircleId(), $userId, $level)) ? $collective : null;
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		} catch (Exception $e) {
			throw new NotFoundException('Failed to run database query.', 0, $e);
		}
	}

	/**
	 * @param string $circleId
	 * @param bool   $includeTrash
	 *
	 * @return Collective|null
	 * @throws NotFoundException
	 */
	public function findByCircleId(string $circleId, bool $includeTrash = false): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleId, IQueryBuilder::PARAM_STR)));
		if (!$includeTrash) {
			$where->add($qb->expr()->isNull('trash_timestamp'));
		}
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		} catch (Exception $e) {
			throw new NotFoundException('Failed to run database query.', 0, $e);
		}
	}

	/**
	 * @param array $circleIds
	 * @param bool  $includeTrash
	 *
	 * @return array
	 */
	public function findByCircleIds(array $circleIds, bool $includeTrash = false): array {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->in('circle_unique_id', $qb->createNamedParameter($circleIds, IQueryBuilder::PARAM_STR_ARRAY)));
		if (!$includeTrash) {
			$where->add($qb->expr()->isNull('trash_timestamp'));
		}
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		try {
			return $this->findEntities($qb);
		} catch (Exception $e) {
			throw new NotFoundException('Failed to run database query', 0, $e);
		}
	}

	/**
	 * @param array  $circleIds
	 * @param string $userId
	 *
	 * @return Collective[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findTrashByCircleIdsAndUser(array $circleIds, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->in('circle_unique_id', $qb->createNamedParameter($circleIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$where->add($qb->expr()->isNotNull('trash_timestamp'));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		try {
			$collectives = $this->findEntities($qb);
		} catch (Exception $e) {
			throw new NotFoundException('Failed to run database query', 0, $e);
		}

		return array_filter($collectives, function (Collective $c) use ($userId) {
			return $this->circleHelper->hasLevel($c->getCircleId(), $userId, Member::LEVEL_ADMIN);
		});
	}

	/**
	 * @param int         $id
	 * @param string|null $userId
	 *
	 * @return Collective|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findByIdAndUser(int $id, string $userId = null): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$where->add($qb->expr()->isNull('trash_timestamp'));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		return $this->findBy($qb, $userId);
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Collective|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findTrashByIdAndUser(int $id, string $userId): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$where->add($qb->expr()->isNotNull('trash_timestamp'));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		return $this->findBy($qb, $userId, Member::LEVEL_ADMIN);
	}

	/**
	 * @return Collective[]
	 */
	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName);
		return $this->findEntities($qb);
	}

	/**
	 * @param string      $circleId
	 * @param string|null $userId
	 * @param bool        $super
	 *
	 * @return string
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function circleIdToName(string $circleId, string $userId = null, bool $super = false): string {
		return $this->circleHelper->getCircle($circleId, $userId, $super)->getSanitizedName();
	}

	/**
	 * @param Collective $collective
	 *
	 * @return Collective
	 */
	public function trash(Collective $collective): Collective {
		$collective->setTrashTimestamp(time());
		return $this->update($collective);
	}

	/**
	 * @param Collective $collective
	 *
	 * @return Collective
	 */
	public function restore(Collective $collective): Collective {
		$collective->setTrashTimestamp(null);
		return $this->update($collective);
	}
}
