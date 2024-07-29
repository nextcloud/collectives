<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Search\FileSearch\FileSearcher;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\ITempManager;
use OCP\Lock\LockedException;
use PDO;
use Psr\Log\LoggerInterface;

class SearchService {
	private const INDICES_DIR_NAME = 'indices';

	public function __construct(
		private CollectiveFolderManager $collectiveFolderManager,
		private ITempManager $tempManager,
		private LoggerInterface $logger,
		private IConfig $config,
	) {
	}

	/**
	 * @throws FileSearchException
	 */
	public function indexCollective(Collective $collective): void {
		$indexPath = $this->tempManager->getTemporaryFile();

		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
		} catch (InvalidPathException|NotFoundException $e) {
			throw new FileSearchException('Collectives search service could not find folder for collective.', 0, $e);
		}

		$indexer = $this->createFileSearcher()->createIndex($indexPath);
		$indexer->runOnDirectory($collectiveFolder);

		$this->saveIndex($collective, $indexPath);
		$this->tempManager->clean();
	}

	/**
	 * @throws FileSearchException
	 */
	public function searchCollective(Collective $collective, string $term, int $maxResults = 15): array {
		if (!$this->areDependenciesMet()) {
			$this->logger->warning('Collectives full-text search is not operational, because the PDO SQLite driver is not available.');
			return [];
		}

		$searcher = $this->createFileSearcher();
		$file = $this->getIndexForCollective($collective);
		if ($file === null) {
			$this->logger->warning('Collectives search failed to find search index for collective with ID ' . $collective->getId());
			return [];
		}

		$searcher->selectIndexFile($file);
		return $searcher->search($term, $maxResults);
	}

	/**
	 * @throws FileSearchException
	 */
	public function getIndexForCollective(Collective $collective): ?File {
		try {
			$file = $this->getIndicesFolder()->get($this->getIndexName($collective));
		} catch (NotFoundException) {
			return null;
		}

		return $file instanceof File ? $file : null;
	}

	public function getIndexName(Collective $collective): string {
		return 'index_' . $collective->getCircleId() . '.db';
	}

	/**
	 * @throws FileSearchException
	 */
	private function saveIndex(Collective $collective, string $path): void {
		$file = $this->getOrCreateIndexForCollective($collective);
		if (!$file) {
			throw new FileSearchException('Could not create index file for collective.');
		}

		try {
			$file->putContent(file_get_contents($path));
		} catch (NotPermittedException|GenericFileException|LockedException $e) {
			throw new FileSearchException('Could not write to index file for collective.', 0, $e);
		}
	}

	/**
	 * @throws FileSearchException
	 */
	private function getOrCreateIndexForCollective(Collective $collective): ?File {
		$file = $this->getIndexForCollective($collective);

		try {
			$file = $this->getIndicesFolder()->newFile($this->getIndexName($collective));
		} catch (NotPermittedException) {
		}

		return $file instanceof File ? $file : null;
	}

	private function createFileSearcher(): FileSearcher {
		$defaultLanguage = $this->config->getSystemValue('default_language', 'en');
		return new FileSearcher(FileSearcher::SUPPORTED_LANGUAGES[$defaultLanguage] ?? null);
	}

	/**
	 * @throws FileSearchException
	 */
	private function getIndicesFolder(): Folder {
		$rootFolder = $this->collectiveFolderManager->getRootFolder();
		try {
			$folder = $rootFolder->get(self::INDICES_DIR_NAME);
			if ($folder instanceof Folder) {
				return $folder;
			}
		} catch (NotFoundException) {
		}

		try {
			return $rootFolder->newFolder(self::INDICES_DIR_NAME);
		} catch (NotPermittedException) {
			throw new FileSearchException('Could not find or create the indices directory.');
		}
	}

	public function areDependenciesMet(): bool {
		return in_array('sqlite', PDO::getAvailableDrivers(), true);
	}
}
