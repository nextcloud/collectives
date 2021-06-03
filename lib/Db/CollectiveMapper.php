<?php

namespace OCA\Collectives\Db;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Exceptions\CircleDoesNotExistException;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Circles\Model\Circle;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\QueryException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Collective insert(Collective $collective)
 * @method Collective delete(Collective $collective)
 * @method Collective findEntity(IQueryBuilder $query)
 * @method Collective update(Collective $collective)
 */
class CollectiveMapper extends QBMapper {

	/**
	 * CollectiveMapper constructor.
	 *
	 * @param IDBConnection  $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives', Collective::class);
	}

	/**
	 * @param IQueryBuilder $qb
	 * @param string|null   $userId
	 * @param bool          $admin
	 *
	 * @return Collective|null
	 */
	private function findBy(IQueryBuilder $qb, string $userId = null, bool $admin = false): ?Collective {
		try {
			$collective = $this->findEntity($qb);
			// Return all found collectives if `$userId` is unset
			if (null === $userId) {
				return $collective;
			}
			// Return all member collectives if `$admin` is false
			if (!$admin) {
				return ($this->isMember($collective, $userId)) ? $collective : null;
			}
			// Return only admin collectives if `$admin` is true
			return ($this->isAdmin($collective, $userId)) ? $collective : null;
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @param string      $circleUniqueId
	 * @param string|null $userId
	 *
	 * @return Collective|null
	 */
	public function findByCircleId(string $circleUniqueId, string $userId = null, bool $includeTrash = false): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId, IQueryBuilder::PARAM_STR)));
		if (!$includeTrash) {
			$where->add($qb->expr()->isNull('trash_timestamp'));
		}
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		return $this->findBy($qb, $userId, false);
	}

	/**
	 * @param string $circleUniqueId
	 * @param string $userId
	 *
	 * @return Collective|null
	 */
	public function findTrashByCircleId(string $circleUniqueId, string $userId): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId, IQueryBuilder::PARAM_STR)));
		$where->add($qb->expr()->isNotNull('trash_timestamp'));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		return $this->findBy($qb, $userId, true);
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
		$where->add($qb->expr()->isNull('trash_timestamp'));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		return $this->findBy($qb, $userId, false);
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Collective|null
	 */
	public function findTrashById(int $id, string $userId): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$where = $qb->expr()->andX();
		$where->add($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$where->add($qb->expr()->isNotNull('trash_timestamp'));
		$qb->select('*')
			->from($this->tableName)
			->where($where);
		return $this->findBy($qb, $userId, true);
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
	 * @param string $circleUniqueId
	 *
	 * @return string
	 * @throws CircleDoesNotExistException
	 */
	public function circleUniqueIdToName(string $circleUniqueId): string {
		return Circles::detailsCircle($circleUniqueId, true)->getName();
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

	/**
	 * Determine if the current user is member of the given collective
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
	 * @param string $name
	 *
	 * @return Circle|null
	 */
	public function findCircle(string $name): ?Circle {
		$circles = Circles::listCircles(
			Circles::CIRCLES_ALL & ~Circles::CIRCLES_PERSONAL,
			$name,
			Circles::LEVEL_ADMIN
		);
		foreach ($circles as $circle) {
			if (strtolower($circle->getName()) === strtolower($name)) {
				return $circle;
			}
		}
		return null;
	}

	/**
	 * Determine if the current user is admin of the given collective
	 * @param Collective $collective
	 * @param string     $userId
	 *
	 * @return bool
	 */
	public function isAdmin(Collective $collective, string $userId): bool {
		try {
			$member = Circles::getMember(
				$collective->getCircleUniqueId(),
				$userId,
				Circles::TYPE_USER);
			// For now only circle owners are admins for the collective
			return ($member !== null && $member->getLevel() >= Circles::LEVEL_OWNER);
		} catch (MemberDoesNotExistException $e) {
			return false;
		}
	}
}
