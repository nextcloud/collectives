<?php

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCA\Collectives\Service\SearchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;
use function method_exists;

class IndexCollectives extends TimedJob {
	/** @var CollectiveMapper */
	private $collectiveMapper;
	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;
	/** @var SearchService */
	private $searchService;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(ITimeFactory $time,
								CollectiveMapper $collectiveMapper,
								CollectiveFolderManager $collectiveFolderManager,
								LoggerInterface $logger,
								SearchService $searchService) {
		parent::__construct($time);

		$this->setInterval(60 * 5);
		// TODO: remove check with NC 24+
		if (method_exists($this, 'setTimeSensitivity')) {
			$this->setTimeSensitivity(self::TIME_INSENSITIVE);
		}

		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->searchService = $searchService;
		$this->logger = $logger;
	}

	/**
	 * @param $argument
	 */
	protected function run($argument): void {
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

	private function isOutdatedIndex(Collective $collective): bool {
		$index = $this->searchService->getIndexForCollective($collective);
		if (!$index) {
			return true;
		}

		try {
			$folder = $this->collectiveFolderManager->getRootFolder()->get($collective->getId());
			if ($folder instanceof Folder) {
				return (bool) $this->findFileNewerThan($folder, $index->getMTime());
			}
		} catch (NotFoundException|InvalidPathException $e) {
		}

		return true;
	}

	private function findFileNewerThan(Folder $folder, int $time): ?File {
		$nodes = [];
		try {
			$nodes = $folder->getDirectoryListing();
		} catch (NotFoundException $e) {
		}

		foreach ($nodes as $node) {
			if ($node instanceof Folder) {
				$file = $this->findFileNewerThan($node, $time);
				if ($file) {
					return $file;
				}
			}

			if ($node instanceof File) {
				try {
					if ($node->getMTime() > $time) {
						return $node;
					}
				} catch (InvalidPathException|NotFoundException $e) {
					return $node;
				}
			}
		}
		return null;
	}
}
