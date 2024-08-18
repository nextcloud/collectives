<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SlugService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class GenerateSlugs implements IRepairStep {
	public function __construct(
		private IAppConfig $config,
		private IDBConnection $connection,
		private CircleHelper $circleHelper,
		private PageService $pageService,
		private SlugService $slugService,
	) {
	}

	public function getName():string {
		return 'Generate slugs for collectives and pages';
	}

	public function run(IOutput $output): void {
		$appVersion = $this->config->getValueString('collectives', 'installed_version');

		if (!$appVersion || !version_compare($appVersion, '2.18.0', '>')) {
			return;
		}

		$output->info('Generating slugs for collectives ...');
		$output->startProgress();
		$this->generateCollectiveSlugs($output);
		$output->finishProgress();
		$output->info('done');

		$output->info('Generating slugs for pages ...');
		$output->startProgress();
		$this->generatePageSlugs($output);
		$output->finishProgress();
		$output->info('done');
	}

	private function generateCollectiveSlugs(IOutput $output): void {
		$query = $this->connection->getQueryBuilder();
		$query->select(['id', 'circle_unique_id'])
			->from('collectives')
			->where('(slug IS NULL OR slug = \'\')');
		$result = $query->executeQuery();

		$update = $this->connection->getQueryBuilder();
		$update->update('collectives')
			->set('slug', $update->createParameter('slug'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		while ($row = $result->fetch()) {
			$circle = $this->circleHelper->getCircle($row['circle_unique_id'], null, true);
			$slug = $this->slugService->generateCollectiveSlug($row['id'], $circle->getSanitizedName());

			$update
				->setParameter('id', (int)$row['id'], IQueryBuilder::PARAM_INT)
				->setParameter('slug', $slug, IQueryBuilder::PARAM_STR)
				->executeStatement();

			$output->advance();
		}
		$result->closeCursor();
	}

	private function generatePageSlugs(IOutput $output): void {
		$queryCollectives = $this->connection->getQueryBuilder();
		$queryCollectives->select(['id', 'circle_unique_id'])
			->from('collectives')
			->where('trash_timestamp IS NULL');
		$resultCollectives = $queryCollectives->executeQuery();

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

				$output->advance();
			}
		}

		$resultCollectives->closeCursor();
	}

}
