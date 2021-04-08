<?php

declare(strict_types=1);

namespace OCA\Collectives\Command;

use OC\Core\Command\Base;
use OCA\Collectives\Db\PageGarbageCollector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeObsoletePages extends Base {
	/** @var PageGarbageCollector */
	private $garbageCollector;

	public function __construct(PageGarbageCollector $garbageCollector) {
		parent::__construct();
		$this->garbageCollector = $garbageCollector;
	}

	protected function configure(): void {
		$this
			->setName('collectives:purge-obsolete-pages')
			->setDescription('Trigger garbage collector for cruft pages in database');
		parent::configure();
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output): void {
		$output->write('Start to purge cruft pages from database ...');
		$count = $this->garbageCollector->purgeObsoletePages();
		$output->writeln('done.');
		$output->writeln(sprintf('Purged %d cruft pages from database.', $count));
	}
}
