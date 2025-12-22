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
 * @method SearchFile insert(SearchFile $file)
 * @method SearchFile update(SearchFile $file)
 * @method SearchFile delete(SearchFile $file)
 * @method SearchFile findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<SearchFile>
 */
class SearchFileMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private IGenerator $snowflake,
	) {
		parent::__construct($db, 'collectives_fts_files', SearchFile::class);
	}

	public function insertFile(string $circleUniqueId, int $fileId, string $path, int $mtime): SearchFile {
		$file = new SearchFile();
		$file->setId($this->snowflake->nextId());
		$file->setCircleUniqueId($circleUniqueId);
		$file->setFileId($fileId);
		$file->setPath($path);
		$file->setMtime($mtime);
		return $this->insert($file);
	}

	public function findByCircleAndFileId(string $circleUniqueId, int $fileId): ?SearchFile {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)))
			->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));

		try {
			return $this->findEntity($qb);
		} catch (\Exception) {
			return null;
		}
	}

	public function getMaxMtimeByCircle(string $circleUniqueId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('mtime'))
			->from($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)));

		$result = $qb->executeQuery();
		$mtime = $result->fetchOne();
		$result->closeCursor();

		return $mtime ? (int)$mtime : null;
	}

	public function deleteByCircle(string $circleUniqueId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)));
		$qb->executeStatement();
	}

	public function deleteByCircleAndFileId(string $circleUniqueId, int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleUniqueId)))
			->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));
		$qb->executeStatement();
	}
}
