<?php

namespace OCA\Collectives\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Mount\CollectiveRootPathHelper;
use OCP\AppFramework\QueryException;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;

class CollectiveService {
	/** @var CollectiveMapper */
	private $collectiveMapper;
	/** @var CollectiveHelper */
	private $collectiveHelper;
	/** @var NodeHelper */
	private $nodeHelper;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var CollectiveRootPathHelper */
	private $collectiveRootPathHelper;

	/**
	 * CollectiveService constructor.
	 *
	 * @param CollectiveMapper         $collectiveMapper
	 * @param CollectiveHelper   $collectiveHelper
	 * @param NodeHelper               $nodeHelper
	 * @param IRootFolder              $rootFolder
	 * @param CollectiveRootPathHelper $collectiveRootPathHelper
	 */
	public function __construct(
		CollectiveMapper $collectiveMapper,
		CollectiveHelper $collectiveHelper,
		NodeHelper $nodeHelper,
		IRootFolder $rootFolder,
		CollectiveRootPathHelper $collectiveRootPathHelper
	) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveHelper = $collectiveHelper;
		$this->nodeHelper = $nodeHelper;
		$this->rootFolder = $rootFolder;
		$this->collectiveRootPathHelper = $collectiveRootPathHelper;
	}

	/**
	 * @param string $userId
	 *
	 * @return array
	 * @throws QueryException
	 */
	public function getCollectives(string $userId): array {
		return $this->collectiveHelper->getCollectivesForUser($userId);
	}

	/**
	 * @param string $userId
	 * @param string $name
	 *
	 * @return Collective
	 */
	public function createCollective(string $userId, string $name): Collective {
		if (empty($name)) {
			throw new \RuntimeException('Empty collective name is not allowed');
		}

		$safeName = $this->nodeHelper->sanitiseFilename($name);

		if (null !== $this->collectiveMapper->findByName($safeName)) {
			throw new \RuntimeException('Collective name already taken: ' . $safeName);
		}

		// Create a new secret circle
		try {
			$circle = Circles::createCircle(2, $safeName);
		} catch (QueryException $e) {
			throw new \RuntimeException('Failed to create Circle ' . $safeName);
		}

		// Create collective object
		$collective = new Collective();
		$collective->setName($name);
		$collective->setCircleUniqueId($circle->getUniqueId());
		$collective = $this->collectiveMapper->insert($collective);

		return $collective;
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 *
	 * @return Collective
	 * @throws NotFoundException
	 */
	public function deleteCollective(string $userId, int $id): Collective {
		if (null === $collective = $this->collectiveMapper->findById($id, $userId)) {
			throw new NotFoundException('Collective not found: '. $id);
		}
		$folder = $this->collectiveMapper->getCollectiveFolder($collective, $userId);

		try {
			Circles::destroyCircle($collective->getCircleUniqueId());
		} catch (QueryException $e) {
			throw new NotFoundException('Circle not found: ' . $collective->getCircleUniqueId());
		}

		try {
			$collectiveFolder = $this->rootFolder->get($this->collectiveRootPathHelper->get() . '/' . $collective->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException | \OCP\Files\NotFoundException | NotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder');
		}

		return $this->collectiveMapper->delete($collective);
	}
}
