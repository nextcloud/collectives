<?php

namespace OCA\Collectives\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\AppFramework\QueryException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;

class CollectiveService {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/**
	 * CollectiveService constructor.
	 *
	 * @param CollectiveMapper         $collectiveMapper
	 * @param CollectiveHelper         $collectiveHelper
	 * @param CollectiveFolderManager  $collectiveFolderManager
	 */
	public function __construct(
		CollectiveMapper $collectiveMapper,
		CollectiveHelper $collectiveHelper,
		CollectiveFolderManager $collectiveFolderManager) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
	}

	/**
	 * @param string $userId
	 *
	 * @return CollectiveInfo[]
	 * @throws QueryException
	 */
	public function getCollectives(string $userId): array {
		return $this->collectiveHelper->getCollectivesForUser($userId);
	}

	/**
	 * @param string $userId
	 * @param string $name
	 *
	 * @return CollectiveInfo
	 * @throws InvalidPathException
	 * @throws FilesNotPermittedException
	 */
	public function createCollective(string $userId, string $userLang, string $name, string $safeName): CollectiveInfo {
		if (empty($name)) {
			throw new UnprocessableEntityException('Empty collective name is not allowed');
		}

		$existing = $this->collectiveMapper->findByName($name, $userId);
		if (null !== $existing) {
			$admin = $this->collectiveMapper->isAdmin($existing, $userId);
			throw new ConflictException(
				'Collective "' . $name . '" exists already.',
				new CollectiveInfo($existing, $admin)
			);
		}

		// Create a new secret circle
		// Will fail if there's a naming conflict
		$circle = $this->collectiveMapper->createCircle($safeName);

		// Create collective object
		$collective = new Collective();
		$collective->setName($name);
		$collective->setCircleUniqueId($circle->getUniqueId());
		$collective = $this->collectiveMapper->insert($collective);

		// Read in collectiveInfo object
		$collectiveInfo = new CollectiveInfo($collective, true);

		// Create folder for collective and optionally copy default landing page
		$this->collectiveFolderManager->createFolder($collective->getId(), $userLang);

		return $collectiveInfo;
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function deleteCollective(string $userId, int $id): Collective {
		if (null === $collective = $this->collectiveMapper->findById($id, $userId)) {
			throw new NotFoundException('Collective not found: ' . $id);
		}

		$circleId = $collective->getCircleUniqueId();
		try {
			$member = Circles::getMember($circleId, $userId, Circles::TYPE_USER);
		} catch (QueryException $e) {
			throw new NotFoundException('Collective not found: ' . $id);
		}

		if ($member->getLevel() < Circles::LEVEL_ADMIN) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to delete collective: ' . $id);
		}

		Circles::destroyCircle($collective->getCircleUniqueId());

		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException | \OCP\Files\NotFoundException | FilesNotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder');
		}

		return new CollectiveInfo($this->collectiveMapper->delete($collective), true);
	}
}
