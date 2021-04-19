<?php

declare(strict_types=1);

namespace OCA\Collectives\Command;

use OC\Core\Command\Base;
use OCA\Collectives\Db\CollectiveGarbageCollector;
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
	 */
	protected function execute(InputInterface $input, OutputInterface $output): void {
		$output->write('Start to purge cruft collectives from database ...');
		$count = $this->garbageCollector->purgeObsoleteCollectives();
		$output->writeln('done.');
		$output->writeln(sprintf('Purged %d cruft collectives from database.', $count));
	}
}
