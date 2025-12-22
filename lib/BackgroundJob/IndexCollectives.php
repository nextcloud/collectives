<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\BackgroundJob;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Search\FileSearch\Db\SearchFileMapper;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCA\Collectives\Service\SearchService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class IndexCollectives extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private CollectiveMapper $collectiveMapper,
		private CollectiveFolderManager $collectiveFolderManager,
		private SearchFileMapper $fileMapper,
		private LoggerInterface $logger,
		private SearchService $searchService,
	) {
		parent::__construct($time);

		$this->setInterval(60 * 5);
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
		try {
			$folder = $this->collectiveFolderManager->getRootFolder()->get((string)$collective->getId());
			$folderMtime = $folder->getMTime();
			$maxIndexedMtime = $this->fileMapper->getMaxMtimeByCircle($collective->getCircleId());

			return $maxIndexedMtime === null || $folderMtime > $maxIndexedMtime;
		} catch (NotFoundException|InvalidPathException) {
			return false;
		}
	}
}
