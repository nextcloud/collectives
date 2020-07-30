<?php

namespace OCA\Wiki\Db;

use OCA\Wiki\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;

/**
 * @method Wiki insert(Wiki $wiki) : Wiki
 * @method Wiki delete(Wiki $wiki) : Wiki
 * @method Wiki findEntity(IQueryBuilder $query) : Wiki
 */
class WikiMapper extends QBMapper {
	private $root;

	public function __construct(
		IRootFolder $root,
		IDBConnection $db) {
		parent::__construct($db, 'wiki', Wiki::class);
		$this->root = $root;
	}

	/**
	 * @param int $wikiId
	 *
	 * @return Folder
	 * @throws NotFoundException
	 */
	public function getWikiFolder(int $wikiId): Folder {
		if (null === $wiki = $this->findById($wikiId)) {
			throw new NotFoundException('wiki ' . $wikiId . ' not found');
		}
		$folders = $this->root->getById($wiki->getFolderId());
		if ([] === $folders || !($folders[0] instanceof Folder)) {
			// TODO: Decide what to do with missing wiki folders
			throw new NotFoundException('wiki folder (FileID ' . $wiki->getFolderId() . ') not found');
		}
		return $folders[0];
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
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
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
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}
}
