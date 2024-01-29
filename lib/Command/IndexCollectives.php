<?php

declare(strict_types=1);

namespace OCA\Collectives\Command;

use OC\Core\Command\Base;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\SearchService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class IndexCollectives extends Base {
	public function __construct(private SearchService $searchService,
		private CollectiveMapper $collectiveMapper) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('collectives:index')
			->setDescription('Indexes collectives for full text search.')
			->addArgument('name', InputArgument::OPTIONAL, 'name of new collective');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$this->searchService->areDependenciesMet()) {
			$output->writeln('<error>Could not index the collectives: PDO or SQLite extension not installed.</error>');
			return 1;
		}

		$collectives = $this->collectiveMapper->getAll();
		$name = $input->getArgument('name');

		foreach ($collectives as $collective) {
			try {
				$circleName = $this->collectiveMapper->circleIdToName($collective->getCircleId(), null, true);

				if ($name && $name !== $circleName) {
					continue;
				}
				$output->writeln('<info>Creating index for ' . $circleName . ' ... </info>');
				$this->searchService->indexCollective($collective);
			} catch (MissingDependencyException|NotFoundException|NotPermittedException) {
				$output->writeln("<error>Failed to find circle associated with collective with ID={$collective->getId()}</error>");
				return 1;
			} catch (FileSearchException) {
				$output->writeln('<error>Failed to save the indices to the collectives folder.</error>');
				return 1;
			}
		}

		return 0;
	}
}
