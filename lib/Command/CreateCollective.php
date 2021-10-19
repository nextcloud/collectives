<?php

declare(strict_types=1);

namespace OCA\Collectives\Command;

use OC\Core\Command\Base;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\CollectiveService;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCollective extends Base {
	/** @var CollectiveService */
	private $collectiveService;

	/** @var NodeHelper */
	private $nodeHelper;

	/** @var IUserManager */
	private $userManager;

	/** @var IUserSession */
	private $userSession;

	/** @var IFactory */
	private $l10nFactory;

	public function __construct(CollectiveService $collectiveService,
								NodeHelper $nodeHelper,
								IUserManager $userManager,
								IUserSession $userSession,
								IFactory $l10nFactory) {
		parent::__construct();
		$this->collectiveService = $collectiveService;
		$this->nodeHelper = $nodeHelper;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->l10nFactory = $l10nFactory;
	}

	protected function configure(): void {
		$this
			->setName('collectives:create')
			->setDescription('Create a new collective')
			->addArgument('name', InputArgument::REQUIRED, 'name of new collective')
			->addOption('owner', '', InputOption::VALUE_REQUIRED, 'userId of owner');
		parent::configure();
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
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
		$safeName = $this->nodeHelper->sanitiseFilename($name);

		$output->write('<info>Creating new collective ' . $name . ' ... </info>');

		[$collective, $info] = $this->collectiveService->createCollective($userId, $lang, $safeName);

		$output->writeln('<info>' . $info ?: 'done.' . '</info>');
		return 0;
	}
}
