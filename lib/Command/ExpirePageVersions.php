<?php

declare(strict_types=1);

namespace OCA\Collectives\Command;

use OC\Core\Command\Base;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpirePageVersions extends Base {
	public function __construct(private CollectiveVersionsExpireManager $expireManager) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('collectives:pages:expire')
			->setDescription('Expire old page versions in collectives');
		parent::configure();
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$output->write('<info>Expiring old page versions ... </info>');
			$this->expireManager->expireAll();
			$output->writeln('<info>done</info>');
			return 0;
		} catch (MissingDependencyException $e) {
			$output->writeln('');
			$output->writeln('<error>  Looks like the circles app is not active.  </error>');
			$output->writeln('<info>  Please enable it:  </info>');
			$output->writeln('<info>      occ app:enable circles  </info>');
			$output->writeln($e->getMessage());
			return 1;
		}
	}
}
