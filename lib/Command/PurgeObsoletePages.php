<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Command;

use OC\Core\Command\Base;
use OCA\Collectives\Db\PageGarbageCollector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeObsoletePages extends Base {
	public function __construct(private PageGarbageCollector $garbageCollector) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('collectives:pages:purge-obsolete')
			->setDescription('Purge cruft pages from database');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->write('Start to purge cruft pages from database ...');
		$count = $this->garbageCollector->purgeObsoletePages();
		$output->writeln('done.');
		$output->writeln(sprintf('Purged %d cruft pages from database.', $count));
		return 0;
	}
}
