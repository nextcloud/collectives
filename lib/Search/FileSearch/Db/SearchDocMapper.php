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

/**
 * @method SearchDoc insert(SearchDoc $doc)
 * @method SearchDoc update(SearchDoc $doc)
 * @method SearchDoc delete(SearchDoc $doc)
 * @method SearchDoc findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<SearchDoc>
 */
class SearchDocMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_s_docs', SearchDoc::class);
	}

	public function insertDoc(int $collectiveId, int $wordId, int $fileId, int $hitCount): SearchDoc {
		$doc = new SearchDoc();
		$doc->setCollectiveId($collectiveId);
		$doc->setWordId($wordId);
		$doc->setFileId($fileId);
		$doc->setHitCount($hitCount);
		return $this->insert($doc);
	}

	public function deleteByCollective(int $collectiveId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)));
		$qb->executeStatement();
	}

	public function findDocumentsByWords(int $collectiveId, array $wordIds, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('d.file_id')
			->selectAlias($qb->func()->sum('d.hit_count'), 'total_hits')
			->from($this->tableName, 'd')
			->innerJoin('d', 'collectives_s_words', 'w', $qb->expr()->eq('d.word_id', 'w.id'))
			->where($qb->expr()->eq('d.collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('d.word_id', $qb->createNamedParameter($wordIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->groupBy('d.file_id')
			->orderBy('total_hits', 'DESC')
			->setMaxResults($limit);

		$result = $qb->executeQuery();
		return $result->fetchAll();
	}

	public function findByCollectiveAndFileId(int $collectiveId, int $fileId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));

		return $this->findEntities($qb);
	}
}
