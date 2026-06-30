<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PageLinkMapper {
	private const TABLE_NAME = 'collectives_page_links';

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @throws Exception
	 */
	public function findByPageId(int $pageId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('linked_page_id')
			->from(self::TABLE_NAME)
			->where($qb->expr()->eq('page_id', $qb->createNamedParameter($pageId)));
		return $qb->executeQuery()->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param int[] $pageIds
	 * @return array<int, int[]> Linked page ids indexed by page id
	 * @throws Exception
	 */
	public function findByPageIds(array $pageIds): array {
		if (empty($pageIds)) {
			return [];
		}

		$linkedPageIds = [];
		foreach (array_chunk($pageIds, 1000) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('page_id', 'linked_page_id')
				->from(self::TABLE_NAME)
				->where($qb->expr()->in('page_id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$linkedPageIds[(int)$row['page_id']][] = (int)$row['linked_page_id'];
			}
			$result->closeCursor();
		}

		return $linkedPageIds;
	}

	/**
	 * @throws Exception
	 */
	public function insertPageLink(int $pageId, int $linkedPageId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE_NAME)
			->setValue('page_id', $qb->createNamedParameter($pageId))
			->setValue('linked_page_id', $qb->createNamedParameter($linkedPageId));
		$qb->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function deletePageLinks(int $pageId, array $linkedPageIds): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE_NAME)
			->where($qb->expr()->eq('page_id', $qb->createNamedParameter($pageId)))
			->andWhere($qb->expr()->in('linked_page_id', $qb->createNamedParameter($linkedPageIds)));
		$qb->executeStatement();
	}

	/**
	 * @throws Exception
	 */
	public function updateByPageId(int $pageId, array $newLinkedPageIds): void {
		if (empty($newLinkedPageIds)) {
			$this->deleteByPageId($pageId);
			return;
		}

		$dbLinkedPageIds = $this->findByPageId($pageId);
		sort($newLinkedPageIds, SORT_NUMERIC);
		sort($dbLinkedPageIds, SORT_NUMERIC);

		$addedLinkedPageIds = array_diff($newLinkedPageIds, $dbLinkedPageIds);
		$removedLinkedPageIds = array_diff($dbLinkedPageIds, $newLinkedPageIds);
		if (empty($addedLinkedPageIds) && empty($removedLinkedPageIds)) {
			return;
		}

		$this->db->beginTransaction();
		foreach ($addedLinkedPageIds as $linkedPageId) {
			$this->insertPageLink($pageId, $linkedPageId);
		}
		if (!empty($removedLinkedPageIds)) {
			$this->deletePageLinks($pageId, $removedLinkedPageIds);
		}
		$this->db->commit();
	}

	/**
	 * @throws Exception
	 */
	public function deleteByPageId(int $pageId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE_NAME)
			->where($qb->expr()->eq('page_id', $qb->createNamedParameter($pageId)));
		$qb->executeStatement();
	}
}
