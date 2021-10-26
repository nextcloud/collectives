<?php

namespace OCA\Collectives\Db;

use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;

class CollectiveGarbageCollector {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/**
	 * CollectiveGarbageCollector constructor.
	 *
	 * @param CollectiveMapper        $collectiveMapper
	 * @param CollectiveFolderManager $collectiveFolderManager
	 */
	public function __construct(CollectiveMapper $collectiveMapper,
								CollectiveFolderManager $collectiveFolderManager) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveFolderManager = $collectiveFolderManager;
	}

	/**
	 * @return int
	 * @throws NotPermittedException
	 */
	public function purgeObsoleteCollectives(): int {
		$purgeCount = 0;
		foreach ($this->collectiveMapper->getAll() as $collective) {
			try {
				$this->collectiveMapper->circleIdToName($collective->getCircleId(), null, true);
			} catch (NotFoundException $e) {
				// Try to find collective folder
				$collectiveFolder = null;
				try {
					$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
				} catch (InvalidPathException | FilesNotFoundException $e) {
				}

				// Delete collective
				$this->collectiveMapper->delete($collective);

				// Delete collective folder if found above
				if ($collectiveFolder) {
					$collectiveFolder->delete();
				}

				$purgeCount++;
			}
		}

		return $purgeCount;
	}
}
