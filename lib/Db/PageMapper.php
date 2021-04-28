<?php

namespace OCA\Collectives\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Page insert(Page $page)
 * @method Page update(Page $page)
 * @method Page delete(Page $page)
 * @method Page findEntity(IQueryBuilder $query)
 */
class PageMapper extends QBMapper {
	/**
	 * PageMapper constructor.
	 *
	 * @param IDBConnection $db
	 * @param string|null   $entityClass
	 */
	public function __construct(IDBConnection $db, string $entityClass = null) {
		parent::__construct($db, 'collectives_pages', $entityClass);
	}

	/**
	 * @param Page $page
	 *
	 * @return Page
	 */
	public function updateOrInsert(Page $page): Page {
		if (null === $page->getId() &&
			null !== $oldPage = $this->findByFileId($page->getFileId())) {
			$page->setId($oldPage->getId());
			return $this->update($page);
		}

		return $this->insert($page);
	}

	/**
	 * @param int $fileId
	 *
	 * @return Page|null
	 */
	public function deleteByFileId(int $fileId): ?Page {
		if (null !== $page = $this->findByFileId($fileId)) {
			return $this->delete($page);
		}
		return null;
	}

	/**
	 * @param int $fileId
	 *
	 * @return Page|null
	 */
	public function findByFileId(int $fileId): ?Page {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT))
			);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * @return Page[]
	 */
	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName);
		return $this->findEntities($qb);
	}
}
