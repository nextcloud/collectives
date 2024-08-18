<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SlugService;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version021500Date20240820000001 extends SimpleMigrationStep {
	private bool $runSlugGeneration = false;

	public function __construct(
		private IDBConnection $connection,
		private CircleHelper $circleHelper,
		private PageService $pageService,
		private SlugService $slugService,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('collectives_pages');
		if (!$table->hasColumn('slug')) {
			$this->runSlugGeneration = true;
			$table->addColumn('slug', Types::STRING, [
				'notnull' => false,
				'default' => false,
				'length' => 255,
			]);

			return $schema;
		}

		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->runSlugGeneration) {
			return;
		}

		$queryCollectives = $this->connection->getQueryBuilder();
		$queryCollectives->select(['id', 'circle_unique_id'])
			->from('collectives')
			->where('trash_timestamp IS NULL');
		$resultCollectives = $queryCollectives->executeQuery();

		$queryPages = $this->connection->getQueryBuilder();
		$queryPages->select(['id'])
			->from('collectives_pages');
		$resultPages = $queryPages->executeQuery();

		$update = $this->connection->getQueryBuilder();
		$update->update('collectives_pages')
			->set('slug', $update->createParameter('slug'))
			->where($update->expr()->eq('file_id', $update->createParameter('file_id')));

		while ($rowCollective = $resultCollectives->fetch()) {
			$circle = $this->circleHelper->getCircle($rowCollective['circle_unique_id'], null, true);
			$pageInfos = $this->pageService->findAll($rowCollective['id'], $circle->getOwner()->getUserId());

			foreach ($pageInfos as $pageInfo) {
				if ($pageInfo->getFileName() === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX) {
					continue;
				}

				$slug = $this->slugService->generatePageSlug($pageInfo->getTitle());
				$update
					->setParameter('file_id', $pageInfo->getId(), IQueryBuilder::PARAM_INT)
					->setParameter('slug', $slug, IQueryBuilder::PARAM_STR)
					->executeStatement();
			}
		}

		$resultCollectives->closeCursor();
		$resultPages->closeCursor();
	}
}
