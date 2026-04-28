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
 * @method SearchFile insert(SearchFile $file)
 * @method SearchFile update(SearchFile $file)
 * @method SearchFile delete(SearchFile $file)
 * @method SearchFile findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<SearchFile>
 */
class SearchFileMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_s_files', SearchFile::class);
	}

	public function insertFile(int $collectiveId, int $fileId, string $path, int $mtime, ?string $language = null): SearchFile {
		$file = new SearchFile();
		$file->setCollectiveId($collectiveId);
		$file->setFileId($fileId);
		$file->setPath($path);
		$file->setMtime($mtime);
		$file->setLanguage($language);
		return $this->insert($file);
	}

	public function findByCollectiveAndFileId(int $collectiveId, int $fileId): ?SearchFile {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));

		try {
			return $this->findEntity($qb);
		} catch (\Exception) {
			return null;
		}
	}

	public function getMaxMtimeByCollective(int $collectiveId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('mtime'))
			->from($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)));

		$result = $qb->executeQuery();
		$mtime = $result->fetchOne();
		$result->closeCursor();

		return $mtime ? (int)$mtime : null;
	}

	public function getLanguagesByCollective(int $collectiveId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('language')
			->from($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->isNotNull('language'));

		$result = $qb->executeQuery();
		$languages = $result->fetchAll(\PDO::FETCH_COLUMN);
		$result->closeCursor();

		return $languages;
	}

	public function deleteByCollective(int $collectiveId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)));
		$qb->executeStatement();
	}

	public function deleteByCollectiveAndFileId(int $collectiveId, int $fileId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId)));
		$qb->executeStatement();
	}
}
