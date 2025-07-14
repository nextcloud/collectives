<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Command;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class GenerateSlugs extends Command {
	public function __construct(
		private IDBConnection $connection,
		private CircleHelper $circleHelper,
		private PageService $pageService,
		private SluggerInterface $slugger,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('collectives:generate-slugs')
			->setDescription('Generate slugs for collectives and pages');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->write('<info>Generating slugs for collectives ... </info>');
		$this->generateCollectiveSlugs();
		$output->writeln('<info>done</info>');

		$output->write('<info>Generating slugs for pages ... </info>');
		$this->generatePageSlugs();
		$output->writeln('<info>done</info>');

		return 0;
	}

	private function generateCollectiveSlugs(): void {
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
		}
		$result->closeCursor();
	}

	private function generatePageSlugs(): void {
		$queryCollectives = $this->connection->getQueryBuilder();
		$queryCollectives->select(['id', 'circle_unique_id'])
			->from('collectives')
			->where($queryCollectives->expr()->isNull('trash_timestamp'));
		$resultCollectives = $queryCollectives->executeQuery();

		$update = $this->connection->getQueryBuilder();
		$update->update('collectives_pages')
			->set('slug', $update->createParameter('slug'))
			->where($update->expr()->eq('file_id', $update->createParameter('file_id')));

		while ($rowCollective = $resultCollectives->fetch()) {
			try {
				$circle = $this->circleHelper->getCircle($rowCollective['circle_unique_id'], null, true);
				$pageInfos = $this->pageService->findAll($rowCollective['id'], $circle->getOwner()->getUserId());
			} catch (NotFoundException|NotPermittedException) {
				// Ignore exceptions from CircleManager (e.g. due to cruft collective without circle)
				continue;
			}

			/** @var PageInfo $pageInfo */
			foreach ($pageInfos as $pageInfo) {
				$pageFile = $this->pageService->getPageFile($rowCollective['id'], $pageInfo->getId(), $circle->getOwner()->getUserId());
				if (NodeHelper::isLandingPage($pageFile)) {
					continue;
				}

				$slug = $this->slugger->slug($pageInfo->getTitle())->toString();
				$update
					->setParameter('file_id', $pageInfo->getId(), IQueryBuilder::PARAM_INT)
					->setParameter('slug', $slug, IQueryBuilder::PARAM_STR)
					->executeStatement();
			}
		}

		$resultCollectives->closeCursor();
	}
}
