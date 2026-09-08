<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Command;

use OCA\Collectives\Service\CollectiveGarbageCollector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PurgeOrphanedCollectives extends Command {
	public function __construct(
		private readonly CollectiveGarbageCollector $garbageCollector,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('collectives:purge-orphaned')
			->setDescription('Purge collectives whose team no longer exists');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->write('Purging orphaned collectives …');
		$count = $this->garbageCollector->purgeOrphanedCollectives();
		$output->writeln('done.');
		$output->writeln(sprintf('Purged %d orphaned collectives.', $count));
		return 0;
	}
}
