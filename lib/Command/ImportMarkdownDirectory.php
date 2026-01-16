<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Command;

use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\ImportService;
use OCA\Collectives\Service\NotFoundException;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportMarkdownDirectory extends Command {
	public function __construct(
		private ImportService $importService,
		private CollectiveService $collectiveService,
		private IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('collectives:import:markdown')
			->setDescription('Import markdown files from a directory to a collective')
			->setHelp('<info>Memory-intensive operation if importing many files. Consider to raise memory limit with `php -d memory_limit=<X>G occ ...`</info>')
			->addArgument('directory', InputArgument::REQUIRED, 'Directory containing markdown files to import')
			->addOption('collective-id', 'c', InputOption::VALUE_REQUIRED, 'Collective ID to import into')
			->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'UserId of collective member performing the import')
			->addOption('parent-id', 'p', InputOption::VALUE_REQUIRED, 'Parent page ID for the import (0 for root)', '0');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$collectiveId = (int)$input->getOption('collective-id');
		$directory = $input->getArgument('directory');
		$userId = $input->getOption('user-id');
		$parentId = (int)$input->getOption('parent-id');
		$verbose = (bool)$input->getOption('verbose');

		if ($collectiveId === 0) {
			$output->writeln('<error>Required option missing: --collective-id=COLLECTIVE_ID</error>');
			return 1;
		}

		if ($userId === null) {
			$output->writeln('<error>Required option missing: --user-id=USER_ID</error>');
			return 1;
		}

		// Verify user exists
		$user = $this->userManager->get($userId);
		if (!$user) {
			$output->writeln('<error>User ' . $userId . ' not found</error>');
			return 1;
		}

		// Verify collective exists
		try {
			$collective = $this->collectiveService->getCollective($collectiveId, $userId);
		} catch (NotFoundException $e) {
			if (str_starts_with($e->getMessage(), 'Circle not found')) {
				$output->writeln('<error>Collective with ID ' . $collectiveId . ' not accessible for user ' . $userId . '.</error>');
			} else {
				$output->writeln('<error>Collective with ID ' . $collectiveId . ' not found.</error>');
			}
			return 1;
		}

		$progressCallback = function (string $status, string $message) use ($output, $verbose) {
			if ($status === 'success') {
				$output->writeln('<info>' . $message . '</info>');
			} elseif ($verbose === true && $status === 'success_verbose') {
				$output->writeln('<info>' . $message . '</info>');
			} elseif ($status === 'error') {
				$output->writeln('<error>' . $message . '</error>');
			} elseif ($verbose === true && $status === 'error_verbose') {
				$output->writeln('<error>' . $message . '</error>');
			}
		};

		try {
			$count = $this->importService->importDirectory($directory, $collective, $parentId, $user, $progressCallback);
		} catch (NotFoundException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}

		$output->writeln('');
		$output->writeln('<info>Processed ' . $count . ' file(s) for collective "' . $collective->getName() . '" (ID: ' . $collectiveId . ').</info>');

		return 0;
	}
}
