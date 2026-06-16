<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method SearchWord insert(SearchWord $word)
 * @method SearchWord update(SearchWord $word)
 * @method SearchWord delete(SearchWord $word)
 * @method SearchWord findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<SearchWord>
 */
class SearchWordMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_s_words', SearchWord::class);
	}

	public function upsert(int $collectiveId, string $term, ?string $stem, int $numHits, int $numFiles): SearchWord {
		$word = $this->findByCollectiveAndTerm($collectiveId, $term);

		if ($word !== null) {
			$word->setNumHits($word->getNumHits() + $numHits);
			$word->setNumFiles($word->getNumFiles() + $numFiles);
			return $this->update($word);
		}

		$word = new SearchWord();
		$word->setCollectiveId($collectiveId);
		$word->setTerm($term);
		$word->setStem($stem);
		$word->setNumHits($numHits);
		$word->setNumFiles($numFiles);
		return $this->insert($word);
	}

	public function deleteByCollective(int $collectiveId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)));
		$qb->executeStatement();
	}

	public function findByCollectiveAndTerm(int $collectiveId, string $term): ?SearchWord {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->eq('term', $qb->createNamedParameter($term)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	public function findByCollectiveAndStem(int $collectiveId, string $stem): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->eq('stem', $qb->createNamedParameter($stem)));

		return $this->findEntities($qb);
	}

	public function findByCollectiveAndPrefix(int $collectiveId, string $prefix, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->like('term', $qb->createNamedParameter($prefix . '%')))
			->orderBy('num_hits', 'DESC')
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	public function decrementCounts(int $collectiveId, string $wordId, int $hitCount): void {
		$qb = $this->db->getQueryBuilder();

		$hitCountParam = $qb->createNamedParameter($hitCount, IQueryBuilder::PARAM_INT);
		$qb->update($this->tableName)
			->set('num_hits', $qb->func()->greatest($qb->createFunction("num_hits - $hitCountParam"), $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->set('num_files', $qb->func()->greatest($qb->createFunction('num_files - 1'), $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($wordId)));
		$qb->executeStatement();

		$this->deleteOrphanedWords($collectiveId);
	}

	private function deleteOrphanedWords(int $collectiveId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->lte('num_hits', $qb->createNamedParameter(0)),
				$qb->expr()->lte('num_files', $qb->createNamedParameter(0))
			));
		$qb->executeStatement();
	}
}
