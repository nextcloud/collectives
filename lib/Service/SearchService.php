<?php

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Search\FileSearch\FileSearcher;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\ITempManager;
use OCP\Lock\LockedException;
use PDO;
use Psr\Log\LoggerInterface;

class SearchService {
	const INDICES_DIR_NAME = 'indices';

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;
	/** @var ITempManager */
	private $tempManager;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(
		CollectiveFolderManager $collectiveFolderManager,
		ITempManager $tempManager,
		LoggerInterface $logger
	) {
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->tempManager = $tempManager;
		$this->logger = $logger;
	}

	/**
	 * @param Collective $collective
	 * @return void
	 * @throws FileSearchException
	 */
	public function indexCollective(Collective $collective): void {
		$indexPath = $this->tempManager->getTemporaryFile();

		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
		} catch (InvalidPathException|\OCP\Files\NotFoundException $e) {
			throw new FileSearchException('Collectives search service could not find folder for collective.', 0, $e);
		}

		$searcher = new FileSearcher();
		$indexer = $searcher->createIndex($indexPath);
		$indexer->runOnDirectory($collectiveFolder);

		$this->saveIndex($collective, $indexPath);
		$this->tempManager->clean();
	}

	/**
	 * @param Collective $collective
	 * @param string $term
	 * @param int $maxResults
	 * @return array
	 * @throws FileSearchException
	 */
	public function searchCollective(Collective $collective, string $term, int $maxResults = 15): array {
		if (!$this->isSqliteAvailable()) {
			$this->logger->warning('Collectives full-text search is not operational, because the sqlite driver not available.');
			return [];
		}

		$searcher = new FileSearcher();
		$file = $this->getIndexForCollective($collective);
		if ($file === null) {
			$this->logger->warning('Collectives search failed to find search index for collective with ID ' . $collective->getId());
			return [];
		}

		$searcher->selectIndexFile($file);
		return $searcher->search($term, $maxResults);
	}

	/**
	 * @param Collective $collective
	 * @return File|null
	 * @throws FileSearchException
	 */
	public function getIndexForCollective(Collective $collective): ?File {
		try {
			$file = $this->getIndicesFolder()->get($this->getIndexName($collective));
		} catch (\OCP\Files\NotFoundException $e) {
			return null;
		}

		return $file instanceof File ? $file : null;
	}

	/**
	 * @param Collective $collective
	 * @return string
	 */
	public function getIndexName(Collective $collective): string {
		return 'index_' . $collective->getCircleId() . '.db';
	}

	/**
	 * @param Collective $collective
	 * @param string $path
	 * @return void
	 * @throws FileSearchException
	 */
	private function saveIndex(Collective $collective, string $path): void {
		$file = $this->getOrCreateIndexForCollective($collective);
		if (!$file) {
			throw new FileSearchException('Could not create index file for collective.');
		}

		try {
			$file->putContent(file_get_contents($path));
		} catch (\OCP\Files\NotPermittedException|GenericFileException|LockedException $e) {
			throw new FileSearchException('Could not write to index file for collective.', 0, $e);
		}
	}

	/**
	 * @param Collective $collective
	 * @return File|null
	 * @throws FileSearchException
	 */
	private function getOrCreateIndexForCollective(Collective $collective): ?File {
		$file = $this->getIndexForCollective($collective);

		try {
			$file = $this->getIndicesFolder()->newFile($this->getIndexName($collective));
		} catch (\OCP\Files\NotPermittedException $e) {
		}

		return $file instanceof File ? $file : null;
	}

	/**
	 * @return Folder
	 * @throws FileSearchException
	 */
	private function getIndicesFolder(): Folder {
		$rootFolder = $this->collectiveFolderManager->getRootFolder();
		try {
			$folder = $rootFolder->get(self::INDICES_DIR_NAME);
			if ($folder instanceof Folder) {
				return $folder;
			}
		} catch (\OCP\Files\NotFoundException $e) {
		}

		try {
			return $rootFolder->newFolder(self::INDICES_DIR_NAME);
		} catch (\OCP\Files\NotPermittedException $e) {
			throw new FileSearchException('Could not find or create the indices directory.');
		}
	}

	/**
	 * @return bool
	 */
	private function isSqliteAvailable(): bool {
		return in_array('sqlite', PDO::getAvailableDrivers(), true);
	}
}
