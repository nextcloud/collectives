<?php

namespace OCA\Collectives\Db;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Model\Circle;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\QueryException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Collective insert(Collective $collective) : Collective
 * @method Collective delete(Collective $collective) : Collective
 * @method Collective findEntity(IQueryBuilder $query) : Collective
 */
class CollectiveMapper extends QBMapper {

	/**
	 * CollectiveMapper constructor.
	 *
	 * @param IDBConnection    $db
	 */
	public function __construct(
		IDBConnection $db) {
		parent::__construct($db, 'collectives', Collective::class);
	}

	/**
	 * @param string      $circleUniqueId
	 * @param string|null $userId
	 *
	 * @return Collective|null
	 */
	public function findByCircleId(string $circleUniqueId, string $userId = null): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId, IQueryBuilder::PARAM_STR)));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		try {
			$collective = $this->findEntity($qb);
			if (null === $userId) {
				return $collective;
			}
			return ($this->isMember($collective, $userId)) ? $collective : null;
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @param int         $id
	 * @param string|null $userId
	 *
	 * @return Collective|null
	 */
	public function findById(int $id, string $userId = null): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		try {
			$collective = $this->findEntity($qb);
			if (null === $userId) {
				return $collective;
			}
			return ($this->isMember($collective, $userId)) ? $collective : null;
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @param string      $name
	 * @param string|null $userId
	 *
	 * @return Collective|null
	 */
	public function findByName(string $name, string $userId = null): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR)));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		try {
			$collective = $this->findEntity($qb);
			if (null === $userId) {
				return $collective;
			}
			return ($this->isMember($collective, $userId)) ? $collective : null;
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @param string $name
	 *
	 * @return Circle
	 */
	public function createCircle(string $name): Circle {
		return Circles::createCircle(Circles::CIRCLES_SECRET, $name);
	}

	/**
	 * @param Collective $collective
	 * @param string     $userId
	 *
	 * @return bool
	 */
	public function isMember(Collective $collective, string $userId): bool {
		try {
			$joinedCircles = Circles::joinedCircles($userId);
			foreach ($joinedCircles as $jc) {
				if ($collective->getCircleUniqueId() === $jc->getUniqueId()) {
					return true;
				}
			}
		} catch (QueryException $e) {
		}
		return false;
	}

	/**
	 * Determine if the current user is admin of the given collective
	 * @param Collective $collective
	 * @param string     $userId
	 *
	 * @return bool
	 */
	public function isAdmin(Collective $collective, string $userId): bool {
		$member = Circles::getMember(
			$collective->getCircleUniqueId(),
			$userId,
			Circles::TYPE_USER);
		// For now only circle owners are admins for the collective
		return ($member !== null && $member->getLevel() >= Circles::LEVEL_OWNER);
	}
}
