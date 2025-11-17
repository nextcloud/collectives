<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Search\FileSearch\FileIndexer;
use OCA\Collectives\Search\FileSearch\FileSearcher;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;

class SearchService {

	public function __construct(
		private FileIndexer $indexer,
		private FileSearcher $searcher,
		private CollectiveFolderManager $collectiveFolderManager,
	) {
	}

	/**
	 * @throws FileSearchException
	 */
	public function indexCollective(Collective $collective, $incremental = false): void {
		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
		} catch (InvalidPathException|NotFoundException $e) {
			throw new FileSearchException('Collectives search service could not find folder for collective.', 0, $e);
		}

		$this->indexer->indexFolder($collectiveFolder, $collective->getCircleId(), $incremental);
	}

	public function searchCollective(Collective $collective, string $query, int $maxResults = 15): array {
		return $this->searcher->search($collective->getCircleId(), $query, $maxResults);
	}

	public function rankByBigrams(string $query, array $files): array {
		return $this->searcher->rankByBigrams($query, $files);
	}
}
