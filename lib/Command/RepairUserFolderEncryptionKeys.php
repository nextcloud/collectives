<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Command;

use OCA\Collectives\Service\UserFolderEncryptionKeyRepairResult;
use OCA\Collectives\Service\UserFolderEncryptionKeyRepairService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RepairUserFolderEncryptionKeys extends Command {
	public function __construct(
		private readonly UserFolderEncryptionKeyRepairService $repairService,
		private readonly IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('collectives:repair:user-folder-encryption-keys')
			->setDescription('Repair collectives encryption key paths for changed or mismatched collectives user folders')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report what would be changed')
			->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'Only repair paths related to the collectives folder used by a specific user');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dryRun = (bool)$input->getOption('dry-run');
		$userId = $input->getOption('user-id');

		if (!is_string($userId) && $userId !== null) {
			$output->writeln('<error>Invalid --user-id value</error>');
			return self::FAILURE;
		}

		if ($userId !== null && $this->userManager->get($userId) === null) {
			$output->writeln('<error>User not found: ' . $userId . '</error>');
			return self::FAILURE;
		}

		$output->writeln('<info>Scanning collectives encryption key paths …</info>');

		$result = $this->repairService->repair(
			$dryRun,
			$userId,
			function (string $level, string $message) use ($output): void {
				if ($level === 'warning') {
					$output->writeln('<comment>' . $message . '</comment>');
					return;
				}

				if ($output->isVerbose()) {
					$output->writeln($message);
				}
			},
		);

		$output->writeln($this->formatSummary($result));

		return $result->hasWarnings() ? self::FAILURE : self::SUCCESS;
	}

	private function formatSummary(UserFolderEncryptionKeyRepairResult $result): string {
		if ($result->getTargetPaths() === 0) {
			return '<info>No collectives user folder paths found for the selected users.</info>';
		}

		$verb = $result->isDryRun() ? 'would create' : 'created';
		$fileVerb = $result->isDryRun() ? 'would copy' : 'copied';

		return sprintf(
			'<info>Processed %d target path(s), found %d source key tree(s), %s %d director%s, %s %d missing key file(s), skipped %d existing file(s).</info>',
			$result->getTargetPaths(),
			$result->getSourceTreesFound(),
			$verb,
			$result->getCreatedDirectories(),
			$result->getCreatedDirectories() === 1 ? 'y' : 'ies',
			$fileVerb,
			$result->getCopiedFiles(),
			$result->getExistingFilesSkipped(),
		);
	}
}
