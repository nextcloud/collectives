<?php

namespace OCA\Unite\Db;

use OCA\Unite\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;

/**
 * @method Collective insert(Collective $collective) : Collective
 * @method Collective delete(Collective $collective) : Collective
 * @method Collective findEntity(IQueryBuilder $query) : Collective
 */
class CollectiveMapper extends QBMapper {
	private $root;

	public function __construct(
		IRootFolder $root,
		IDBConnection $db) {
		parent::__construct($db, 'unite', Collective::class);
		$this->root = $root;
	}

	/**
	 * @param int $collectiveId
	 *
	 * @return Folder
	 * @throws NotFoundException
	 */
	public function getCollectiveFolder(int $collectiveId): Folder {
		if (null === $collective = $this->findById($collectiveId)) {
			throw new NotFoundException('Collective ' . $collectiveId . ' not found');
		}
		$folders = $this->root->getById($collective->getFolderId());
		if ([] === $folders || !($folders[0] instanceof Folder)) {
			// TODO: Decide what to do with missing collective folders
			throw new NotFoundException('Collective folder (FileID ' . $collective->getFolderId() . ') not found');
		}
		return $folders[0];
	}

	/**
	 * @param string $circleUniqueId
	 *
	 * @return Collective|null
	 */
	public function findByCircleId(string $circleUniqueId): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId, IQueryBuilder::PARAM_STR))
			);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @param int $id
	 *
	 * @return \OCA\Unite\Db\Collective|null
	 */
	public function findById(int $id): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}
}
