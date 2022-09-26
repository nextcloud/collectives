<?php

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCA\Collectives\Service\SearchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class IndexCollectives extends TimedJob {
	/** @var CollectiveMapper */
	private $collectiveMapper;
	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;
	/** @var SearchService */
	private $searchService;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ITimeFactory $time
	 * @param CollectiveMapper $collectiveMapper
	 * @param CollectiveFolderManager $collectiveFolderManager
	 * @param LoggerInterface $logger
	 * @param SearchService $searchService
	 */
	public function __construct(ITimeFactory $time,
								CollectiveMapper $collectiveMapper,
								CollectiveFolderManager $collectiveFolderManager,
								LoggerInterface $logger,
								SearchService $searchService) {
		parent::__construct($time);
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->searchService = $searchService;
		$this->logger = $logger;

		$this->setInterval(60 * 5);
	}

	/**
	 * @param $argument
	 */
	protected function run($argument): void {
		if (!$this->searchService->areDependenciesMet()) {
			return;
		}

		$collectives = $this->collectiveMapper->getAll();
		foreach ($collectives as $collective) {
			if ($this->isOutdatedIndex($collective)) {
				try {
					$this->searchService->indexCollective($collective);
				} catch (FileSearchException $e) {
					$this->logger->error('Collectives background job failed to index collective ' . $collective->getId(), [
						'message' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					]);
				}
			}
		}
	}

	/**
	 * @param Collective $collective
	 * @return bool
	 */
	private function isOutdatedIndex(Collective $collective): bool {
		$index = $this->searchService->getIndexForCollective($collective);
		if (!$index) {
			return true;
		}

		try {
			$folder = $this->collectiveFolderManager->getRootFolder()->get((string) $collective->getId());
			return $folder->getMTime() > $index->getMTime();
		} catch (NotFoundException|InvalidPathException $e) {
			return false;
		}
	}
}
