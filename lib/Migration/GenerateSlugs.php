<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Symfony\Component\String\Slugger\SluggerInterface;

class GenerateSlugs implements IRepairStep {
	public function __construct(
		private IAppConfig $config,
		private IDBConnection $connection,
		private CircleHelper $circleHelper,
		private CollectiveFolderManager $collectiveFolderManager,
		private SluggerInterface $slugger,
	) {
	}

	public function getName():string {
		return 'Generate slugs for collectives and pages';
	}

	public function run(IOutput $output): void {
		if ($this->config->getValueBool('collectives', 'migrated_slugs')) {
			$output->info('Slugs already generated');
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

		$this->config->setValueBool('collectives', 'migrated_slugs', true);
	}

	private function generateCollectiveSlugs(IOutput $output): void {
		$query = $this->connection->getQueryBuilder();
		$query->select(['id', 'circle_unique_id'])
			->from('collectives')
			->where($query->expr()->orX(
				$query->expr()->isNull('slug'),
				$query->expr()->emptyString('slug')
			));
		$result = $query->executeQuery();

		$update = $this->connection->getQueryBuilder();
		$update->update('collectives')
			->set('slug', $update->createParameter('slug'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		while ($row = $result->fetch()) {
			try {
				$circle = $this->circleHelper->getCircle($row['circle_unique_id'], null, true);
			} catch (NotFoundException|NotPermittedException) {
				// Ignore exceptions from CircleManager (e.g. due to cruft collective without circle)
				continue;
			}
			$slug = $this->slugger->slug($circle->getSanitizedName())->toString();

			$update
				->setParameter('id', (int)$row['id'], IQueryBuilder::PARAM_INT)
				->setParameter('slug', $slug, IQueryBuilder::PARAM_STR)
				->executeStatement();

			$output->advance();
		}
		$result->closeCursor();
	}

	private function generatePageSlugs(IOutput $output): void {
		$query = $this->connection->getQueryBuilder();
		$query->select(['id', 'file_id'])
			->from('collectives_pages')
			->where($query->expr()->orX(
				$query->expr()->isNull('slug'),
				$query->expr()->emptyString('slug')
			));
		$result = $query->executeQuery();


		$update = $this->connection->getQueryBuilder();
		$update->update('collectives_pages')
			->set('slug', $update->createParameter('slug'))
			->where($update->expr()->eq('file_id', $update->createParameter('file_id')));

		$rootFolder = $this->collectiveFolderManager->getRootFolder();

		while ($row = $result->fetch()) {
			$pageFile = $rootFolder->getById($row['file_id'])[0];
			if (!($pageFile instanceof File) || NodeHelper::isLandingPage($pageFile)) {
				continue;
			}

			$slug = $this->slugger->slug(
				NodeHelper::isIndexPage($pageFile)
					? $pageFile->getParent()->getName()
					: basename($pageFile->getName(), PageInfo::SUFFIX)
			);
			$update
				->setParameter('file_id', $pageFile->getId(), IQueryBuilder::PARAM_INT)
				->setParameter('slug', $slug, IQueryBuilder::PARAM_STR)
				->executeStatement();

			$output->advance();
		}

		$result->closeCursor();
	}
}
