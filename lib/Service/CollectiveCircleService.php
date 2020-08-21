<?php

namespace OCA\Unite\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Unite\Db\Collective;
use OCA\Unite\Db\CollectiveMapper;
use OCA\Unite\Fs\NodeHelper;
use OCP\AppFramework\QueryException;

class CollectiveCircleService {
	/** @var CollectiveMapper */
	private $collectiveMapper;
	/** @var CollectiveCircleHelper */
	private $collectiveCircleHelper;
	/** @var NodeHelper */
	private $nodeHelper;

	/**
	 * CollectiveCircleService constructor.
	 *
	 * @param CollectiveMapper       $collectiveMapper
	 * @param CollectiveCircleHelper $collectiveCircleHelper
	 * @param NodeHelper             $nodeHelper
	 */
	public function __construct(
		CollectiveMapper $collectiveMapper,
		CollectiveCircleHelper $collectiveCircleHelper,
		NodeHelper $nodeHelper
	) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveCircleHelper = $collectiveCircleHelper;
		$this->nodeHelper = $nodeHelper;
	}

	/**
	 * @param string $userId
	 *
	 * @return array
	 * @throws QueryException
	 */
	public function getCollectives(string $userId): array {
		return $this->collectiveCircleHelper->getCollectivesForUser($userId);
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

		$collective = $this->collectiveMapper->delete($collective);

		// TODO: Remove folder at $this->collectiveRootPathHelper->get() . $id
		/* try {
			$folder->delete();
		} catch (InvalidPathException | \OCP\Files\NotFoundException | NotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder: ' . $id);
		} */

		return $collective;
	}
}
