<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCA\Collectives\Model\FileInfo;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class FileCacheMapper {
	private const BATCH_SIZE = 1000;

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * Query filecache for file data by file IDs
	 *
	 * @param int[] $fileIds
	 * @return array<int, FileInfo> Indexed by fileId
	 */
	public function findByFileIds(array $fileIds): array {
		if (empty($fileIds)) {
			return [];
		}

		$fileInfos = [];
		foreach (array_chunk($fileIds, self::BATCH_SIZE) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('fileid', 'storage', 'parent', 'path', 'name', 'mimetype', 'mimepart', 'size', 'mtime', 'storage_mtime', 'etag', 'encrypted', 'permissions', 'checksum', 'unencrypted_size')
				->from('filecache')
				->where($qb->expr()->in('fileid', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$fileInfo = new FileInfo(
					fileId: (int)$row['fileid'],
					storage: (int)$row['storage'],
					path: (string)$row['path'],
					parent: (int)$row['parent'],
					name: (string)$row['name'],
					mimetype: (int)$row['mimetype'],
					mimepart: (int)$row['mimepart'],
					size: (int)$row['size'],
					mtime: (int)$row['mtime'],
					storage_mtime: (int)$row['storage_mtime'],
					encrypted: (int)$row['encrypted'],
					unencrypted_size: (int)$row['unencrypted_size'],
					etag: (string)$row['etag'],
					permissions: (int)$row['permissions'],
					checksum: $row['checksum'] !== null ? (string)$row['checksum'] : null,
				);
				$fileInfos[$fileInfo->fileId] = $fileInfo;
			}
			$result->closeCursor();
		}

		return $fileInfos;
	}
}
