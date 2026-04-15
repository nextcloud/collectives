<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use OCA\Collectives\Fs\NodeHelper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class PopulatePageCollectiveId implements IRepairStep {
	private const BATCH_SIZE = 1000;

	public function __construct(
		private IAppConfig $config,
		private IDBConnection $connection,
	) {
	}

	public function getName(): string {
		return 'Populate collective_id for existing pages';
	}

	public function run(IOutput $output): void {
		if ($this->config->getValueBool('collectives', 'migrated_collective_id')) {
			$output->info('collective_id already populated');
			return;
		}

		$output->info('Populating collective_id for pages ...');

		$fileIds = $this->getFileIdsWithoutCollectiveId();
		$output->startProgress(count($fileIds));

		if (empty($fileIds)) {
			$output->finishProgress();
			$output->info('No pages need updating');
			$this->config->setValueBool('collectives', 'migrated_collective_id', true);
			return;
		}

		foreach (array_chunk($fileIds, self::BATCH_SIZE) as $chunk) {
			$this->processBatch($chunk, $output);
		}

		$output->finishProgress();
		$output->info('done');

		$this->config->setValueBool('collectives', 'migrated_collective_id', true);
	}

	/**
	 * Get all file_ids that have null collective_id
	 *
	 * @return list<int>
	 */
	private function getFileIdsWithoutCollectiveId(): array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('file_id')
			->from('collectives_pages')
			->where($qb->expr()->isNull('collective_id'));

		$result = $qb->executeQuery();
		$fileIds = [];
		while ($row = $result->fetch()) {
			$fileIds[] = (int)$row['file_id'];
		}
		$result->closeCursor();

		return $fileIds;
	}

	/**
	 * @param list<int> $fileIds
	 */
	private function processBatch(array $fileIds, IOutput $output): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->select(['fileid', 'path'])
			->from('filecache')
			->where($qb->expr()->in('fileid', $qb->createNamedParameter($fileIds, IQueryBuilder::PARAM_INT_ARRAY)));

		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$fileId = (int)$row['fileid'];
			$collectiveId = NodeHelper::extractCollectiveIdFromPath($row['path']);
			if (!$collectiveId) {
				$output->warning("Could not extract collective_id for file_id $fileId with path {$row['path']}");
				continue;
			}

			$this->updatePage($fileId, $collectiveId);
		}
	}

	private function updatePage(int $fileId, int $collectiveId): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->update('collectives_pages')
			->set('collective_id', $qb->createParameter('collective_id'))
			->where($qb->expr()->eq('file_id', $qb->createParameter('file_id')));

		$qb->setParameter('file_id', $fileId, IQueryBuilder::PARAM_INT)
			->setParameter('collective_id', $collectiveId, IQueryBuilder::PARAM_INT)
			->executeStatement();
	}
}
