<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
 * @template-extends QBMapper<Page>
 */
class PageMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_pages', Page::class);
	}

	public function updateOrInsert(Page $page): Page {
		if ($page->getId() === null
			&& null !== $oldPage = $this->findByFileId($page->getFileId())) {
			$page->setId($oldPage->getId());
			return $this->update($page);
		}

		return $this->insert($page);
	}

	public function trashByFileId(int $fileId): ?Page {
		if (null !== $page = $this->findByFileId($fileId)) {
			$page->setTrashTimestamp(time());
			return $this->update($page);
		}
		return null;
	}

	public function restoreByFileId(int $fileId): ?Page {
		if (null !== $page = $this->findByFileId($fileId, true)) {
			$page->setTrashTimestamp(null);
			return $this->update($page);
		}
		return null;
	}

	public function deleteByFileId(int $fileId): ?Page {
		if (null !== $page = $this->findByFileId($fileId, true)) {
			return $this->delete($page);
		}
		return null;
	}

	public function findByFileId(int $fileId, bool $trashed = false): ?Page {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)),
		];
		if ($trashed) {
			$andX[] = $qb->expr()->isNotNull('trash_timestamp');
		} else {
			$andX[] = $qb->expr()->isNull('trash_timestamp');
		}
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * @param int[] $fileIds
	 * @return array<int, Page> Indexed by file_id
	 */
	public function findByFileIds(array $fileIds, bool $trashed = false): array {
		if (empty($fileIds)) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->in('file_id',
				$qb->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)));

		if ($trashed) {
			$qb->andWhere($qb->expr()->isNotNull('trash_timestamp'));
		} else {
			$qb->andWhere($qb->expr()->isNull('trash_timestamp'));
		}

		$pages = $this->findEntities($qb);
		$pagesByFileId = [];
		foreach ($pages as $page) {
			$pagesByFileId[$page->getFileId()] = $page;
		}

		return $pagesByFileId;
	}

	/**
	 * @return Page[]
	 */
	public function findByCollectiveId(int $collectiveId, bool $trashed = false): array {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)),
		];
		// fixme: change index to use timestamp as well?
		if ($trashed) {
			$andX[] = $qb->expr()->isNotNull('trash_timestamp');
		} else {
			$andX[] = $qb->expr()->isNull('trash_timestamp');
		}
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		return $this->findEntities($qb);
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
