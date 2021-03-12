<?php

namespace OCA\Collectives\Db;

use OC\User\NoUserException;
use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\QueryException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Folder;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;

/**
 * @method Collective insert(Collective $collective) : Collective
 * @method Collective delete(Collective $collective) : Collective
 * @method Collective findEntity(IQueryBuilder $query) : Collective
 */
class CollectiveMapper extends QBMapper {
	/** @var UserFolderHelper */
	private $userFolderHelper;

	/**
	 * CollectiveMapper constructor.
	 *
	 * @param IDBConnection    $db
	 * @param UserFolderHelper $userFolderHelper
	 */
	public function __construct(
		IDBConnection $db,
		UserFolderHelper $userFolderHelper) {
		parent::__construct($db, 'collectives', Collective::class);
		$this->userFolderHelper = $userFolderHelper;
	}

	/**
	 * @param Collective $collective
	 * @param string     $userId
	 *
	 * @return Folder
	 * @throws NotFoundException
	 */
	public function getCollectiveFolder(Collective $collective, string $userId): Folder {
		try {
			$folder = $this->userFolderHelper->getFolder($userId)->get($collective->getName());
		} catch (\OCP\Files\NotFoundException | NotPermittedException | NoUserException $e) {
			throw new NotFoundException('Folder not found for collective ' . $collective->getName());
		}

		if (!($folder instanceof Folder)) {
			throw new NotFoundException('Folder not found for collective ' . $collective->getName());
		}
		return $folder;
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
}
