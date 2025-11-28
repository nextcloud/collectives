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
use OCP\Snowflake\IGenerator;

/**
 * @method SearchWord insert(SearchWord $word)
 * @method SearchWord update(SearchWord $word)
 * @method SearchWord delete(SearchWord $word)
 * @method SearchWord findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<SearchWord>
 */
class SearchWordMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IGenerator $snowflake,
	) {
		parent::__construct($db, 'collectives_fts_words', SearchWord::class);
	}

	public function upsert(string $circleUniqueId, string $term, int $numHits, int $numFiles): SearchWord {
		$word = $this->findByCircleAndTerm($circleUniqueId, $term);

		if ($word !== null) {
			$word->setNumHits($word->getNumHits() + $numHits);
			$word->setNumFiles($word->getNumFiles() + $numFiles);
			return $this->update($word);
		}

		$word = new SearchWord();
		$word->setId($this->snowflake->nextId());
		$word->setCircleUniqueId($circleUniqueId);
		$word->setTerm($term);
		$word->setNumHits($numHits);
		$word->setNumFiles($numFiles);
		return $this->insert($word);
	}

	public function deleteByCircle(string $circleUniqueId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)));
		$qb->executeStatement();
	}

	public function findByCircleAndTerm(string $circleUniqueId, string $term): ?SearchWord {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)))
			->andWhere($qb->expr()->eq('term', $qb->createNamedParameter($term)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	public function findByCircleAndPrefix(string $circleUniqueId, string $prefix, int $limit = 20): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)))
			->andWhere($qb->expr()->like('term', $qb->createNamedParameter($prefix . '%')))
			->orderBy('num_hits', 'DESC')
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	public function decrementCounts(string $circleUniqueId, string $wordId, int $hitCount): void {
		$qb = $this->db->getQueryBuilder();

		$hitCountParam = $qb->createNamedParameter($hitCount, IQueryBuilder::PARAM_INT);
		$qb->update($this->tableName)
			->set('num_hits', $qb->createFunction("num_hits - $hitCountParam"))
			->set('num_files', $qb->createFunction('num_files - 1'))
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($wordId)));
		$qb->executeStatement();

		$this->deleteZeroHitWords($circleUniqueId);
	}

	private function deleteZeroHitWords(string $circleUniqueId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)))
			->andWhere($qb->expr()->lte('num_hits', $qb->createNamedParameter(0)));
		$qb->executeStatement();
	}
}
