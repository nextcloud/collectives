<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Command;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\CollectiveService;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCollective extends Command {
	public function __construct(
		private CollectiveService $collectiveService,
		private NodeHelper $nodeHelper,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private IFactory $l10nFactory,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('collectives:create')
			->setDescription('Create a new collective')
			->addArgument('name', InputArgument::REQUIRED, 'name of new collective')
			->addOption('owner', '', InputOption::VALUE_REQUIRED, 'userId of owner');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		$userId = $input->getOption('owner');

		if ($userId === null) {
			$output->writeln('<error>Required option missing: --owner=OWNER</error>');
			return 1;
		}

		$user = $this->userManager->get($userId);
		$this->userSession->setUser($user);
		$lang = $this->l10nFactory->getUserLanguage($this->userSession->getUser());

		$output->write('<info>Creating new collective ' . $name . ' …</info>');

		[, $info] = $this->collectiveService->createCollective($userId, $lang, $name);

		$output->writeln('<info>' . $info ?: 'done.' . '</info>');
		return 0;
	}
}
