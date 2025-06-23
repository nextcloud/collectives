<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Command;

use OC;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Trash\PageTrashBackend;
use OCP\App\IAppManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class PageTrashCleanup extends Command {
	private ?PageTrashBackend $trashBackend = null;

	public function __construct(
		IAppManager $appManager,
		private CollectiveMapper $collectiveMapper,
	) {
		parent::__construct();
		if ($appManager->isEnabledForUser('files_trashbin')) {
			$this->trashBackend = OC::$server->get(PageTrashBackend::class);
		}
	}

	protected function configure(): void {
		$this
			->setName('collectives:pages:trashbin:cleanup')
			->setDescription('Empty the collectives page trashbin.')
			->addArgument('collective', InputArgument::OPTIONAL, 'name of collective')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'skip confirmation');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if (!$this->trashBackend) {
			$output->writeln('<error>files_trashbin is disabled: collectives page trashbin is not available</error>');
			return 1;
		}
		/** @var QuestionHelper $helper */
		$helper = $this->getHelper('question');

		$collectives = $this->collectiveMapper->getAll();
		if ($input->getArgument('collective') !== null) {
			$collectiveName = $input->getArgument('collective');

			foreach ($collectives as $collective) {
				$foundCollectiveName = null;
				try {
					$foundCollectiveName = $this->collectiveMapper->idToName($collective->getId(), null, true);
				} catch (MissingDependencyException|NotFoundException|NotPermittedException) {
				}

				if ($foundCollectiveName === $collectiveName) {
					/** @var Question $question */
					$question = new ConfirmationQuestion('Are you sure you want to empty the page trashbin of collective ' . $collectiveName . '? This can not be undone. (y/N) ', false);
					if (!$input->getOption('force') && !$helper->ask($input, $output, $question)) {
						return -2;
					}

					$this->trashBackend->cleanTrashFolder($collective->getId());
					return 0;
				}
			}

			$output->writeln('<error>Collective not found: ' . $collectiveName . '</error>');
			return -1;
		}

		/** @var Question $question */
		$question = new ConfirmationQuestion('Are you sure you want to empty the page trashbin of all collectives? This can not be undone (y/N).', false);
		if (!$input->getOption('force') && !$helper->ask($input, $output, $question)) {
			return -2;
		}

		foreach ($collectives as $collective) {
			$this->trashBackend->cleanTrashFolder($collective->getId());
		}

		return 0;
	}
}
