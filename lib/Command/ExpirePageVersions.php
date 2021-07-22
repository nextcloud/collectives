<?php

declare(strict_types=1);

namespace OCA\Collectives\Command;

use OC\Core\Command\Base;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExpirePageVersions extends Base {
	/** @var CollectiveVersionsExpireManager */
	private $expireManager;

	public function __construct(CollectiveVersionsExpireManager $expireManager) {
		parent::__construct();
		$this->expireManager = $expireManager;
	}

	protected function configure(): void {
		$this
			->setName('collectives:pages:expire')
			->setDescription('Expire old page versions in collectives');
		parent::configure();
	}

	/**
	 * @param InputInterface  $input
	 * @param OutputInterface $output
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->expireManager->listen(CollectiveVersionsExpireManager::class, 'enterFolder', function (array $folder) use ($output) {
			$output->writeln("<info>Expiring old page versions in '${folder['mount_point']}'</info>");
		});
		$this->expireManager->listen(CollectiveVersionsExpireManager::class, 'deleteVersion', function (IVersion $version) use ($output) {
			$id = $version->getRevisionId();
			$file = $version->getSourceFileName();
			$output->writeln("<info>Expiring page version $id for '$file'</info>");
		});

		$this->expireManager->listen(CollectiveVersionsExpireManager::class, 'deleteFile', function ($id) use ($output) {
			$output->writeln("<info>Cleaning up page versions for no longer existing file with id $id</info>");
		});

		$this->expireManager->expireAll();
		return 0;
	}
}
