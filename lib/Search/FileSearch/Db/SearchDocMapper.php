<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Snowflake\IGenerator;

/**
 * @method SearchDoc insert(SearchDoc $doc)
 * @method SearchDoc update(SearchDoc $doc)
 * @method SearchDoc delete(SearchDoc $doc)
 * @method SearchDoc findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<SearchDoc>
 */
class SearchDocMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IGenerator $snowflake,
	) {
		parent::__construct($db, 'collectives_fts_docs', SearchDoc::class);
	}

	public function insertDoc(string $circleUniqueId, string $wordId, int $fileId, int $hitCount): SearchDoc {
		$doc = new SearchDoc();
		$doc->setId($this->snowflake->nextId());
		$doc->setCircleUniqueId($circleUniqueId);
		$doc->setWordId($wordId);
		$doc->setFileId($fileId);
		$doc->setHitCount($hitCount);
		return $this->insert($doc);
	}

	public function deleteByCircle(string $circleUniqueId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)));
		$qb->executeStatement();
	}

	public function findDocumentsByWords(string $circleUniqueId, array $wordIds, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('file_id')
			->selectAlias($qb->func()->sum('hit_count'), 'total_hits')
			->from($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)))
			->andWhere($qb->expr()->in('word_id', $qb->createNamedParameter($wordIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->groupBy('file_id')
			->orderBy('total_hits', 'DESC')
			->setMaxResults($limit);

		$result = $qb->executeQuery();
		return $result->fetchAll();
	}

	public function findByCircleAndFileId(string $circleUniqueId, int $fileId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)))
			->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));

		return $this->findEntities($qb);
	}
}
