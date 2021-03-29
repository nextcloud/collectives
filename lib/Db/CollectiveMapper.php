<?php

namespace OCA\Collectives\Db;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Model\Circle;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
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
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId, IQueryBuilder::PARAM_STR))
			);
		try {
			$collective = $this->findEntity($qb);
			return (null === $userId) ? $collective : $this->userHasCollective($collective, $userId);
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
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		try {
			$collective = $this->findEntity($qb);
			return (null === $userId) ? $collective : $this->userHasCollective($collective, $userId);
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
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('name', $qb->createNamedParameter($name, IQueryBuilder::PARAM_STR))
			);
		try {
			$collective = $this->findEntity($qb);
			return (null === $userId) ? $collective : $this->userHasCollective($collective, $userId);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @param Collective $collective
	 * @param string     $userId
	 *
	 * @return Collective|null
	 */
	public function userHasCollective(Collective $collective, string $userId): ?Collective {
		try {
			$joinedCircles = Circles::joinedCircles($userId);
			foreach ($joinedCircles as $jc) {
				if ($collective->getCircleUniqueId() === $jc->getUniqueId()) {
					return $collective;
				}
			}
		} catch (QueryException | MemberDoesNotExistException $e) {
			return null;
		}
		return null;
	}

	/**
	 * @param string     $name
	 *
	 * @return Circle|null
	 * @throws \RuntimeException
	 */
	public function createCircle(string $name): Circle {
		try {
			return Circles::createCircle(2, $name);
		} catch (QueryException $e) {
			throw new \RuntimeException('Failed to create Circle ' . $name);
		}
	}
}
