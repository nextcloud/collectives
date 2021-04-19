<?php

namespace OCA\Collectives\Db;

use OCA\Circles\Exceptions\CircleDoesNotExistException;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;

class CollectiveGarbageCollector {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/**
	 * CollectiveGarbageCollector constructor.
	 *
	 * @param CollectiveMapper $collectiveMapper
	 */
	public function __construct(CollectiveMapper $collectiveMapper,
								CollectiveFolderManager $collectiveFolderManager) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveFolderManager = $collectiveFolderManager;
	}

	/**
	 * @return int
	 */
	public function purgeObsoleteCollectives(): int {
		$purgeCount = 0;
		foreach ($this->collectiveMapper->getAll() as $collective) {
			try {
				$this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId());
			} catch (CircleDoesNotExistException $e) {
				// Try to find collective folder
				$collectiveFolder = null;
				try {
					$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
				} catch (InvalidPathException | NotFoundException $e) {
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
