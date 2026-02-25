<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use Symfony\Component\Console\Output\OutputInterface;

class ProgressReporter implements IProgressReporter {
	public function __construct(
		private readonly OutputInterface $output,
		private readonly bool $verbose = false,
	) {
	}

	public function writeInfo(string $message): void {
		$this->output->writeln('<info>' . $message . '</info>');
	}

	public function writeInfoVerbose(string $message): void {
		if ($this->verbose) {
			$this->output->writeln('<info>' . $message . '</info>');
		}
	}

	public function writeError(string $message): void {
		$this->output->writeln('<error>' . $message . '</error>');
	}

	public function writeErrorVerbose(string $message): void {
		if ($this->verbose) {
			$this->output->writeln('<error>' . $message . '</error>');
		}
	}
}
