<?php

declare(strict_types=1);

namespace OCA\Collectives\Command;

use OC\Core\Command\Base;
use OCA\Collectives\Db\CollectiveGarbageCollector;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\MissingDependencyException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeObsoleteCollectives extends Base {
	/** @var CollectiveGarbageCollector */
	private $garbageCollector;

	public function __construct(CollectiveGarbageCollector $garbageCollector) {
		parent::__construct();
		$this->garbageCollector = $garbageCollector;
	}

	protected function configure(): void {
		$this
			->setName('collectives:purge-obsolete')
			->setDescription('Purge cruft collectives from database');
		parent::configure();
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws NotPermittedException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->write('Start to purge cruft collectives from database ...');
		try {
			$count = $this->garbageCollector->purgeObsoleteCollectives();
			$output->writeln('done.');
			$output->writeln(sprintf('Purged %d cruft collectives from database.', $count));
			return 0;
		} catch (MissingDependencyException $e) {
			$output->writeln('');
			$output->writeln('<error>  Looks like the circles app is not active.  </error>');
			$output->writeln('<info>  Please enable it:  </info>');
			$output->writeln('<info>      occ app:enable circles  </info>');
			$output->writeln($e->getMessage());
			return 1;
		}
	}
}
