<?php

namespace OCA\Collectives\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Exceptions\CircleAlreadyExistsException;
use OCA\Circles\Exceptions\CircleDoesNotExistException;
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
	 *
	 * @return CollectiveInfo[]
	 * @throws QueryException
	 */
	public function getCollectivesTrash(string $userId): array {
		return $this->collectiveHelper->getCollectivesTrashForUser($userId);
	}

	/**
	 * @param string $userId
	 * @param string $userLang
	 * @param string $name
	 * @param string $safeName
	 *
	 * @return CollectiveInfo
	 * @throws FilesNotPermittedException
	 * @throws InvalidPathException
	 * @throws UnprocessableEntityException
	 * @throws CircleAlreadyExistsException
	 */
	public function createCollective(string $userId, string $userLang, string $name, string $safeName): CollectiveInfo {
		if (empty($name) || empty($safeName)) {
			throw new UnprocessableEntityException('Empty collective name is not allowed');
		}

		// Create a new secret circle
		// Will fail if there's a naming conflict
		$circle = $this->collectiveMapper->createCircle($safeName);

		// Create collective object
		$collective = new Collective();
		$collective->setCircleUniqueId($circle->getUniqueId());
		$collective = $this->collectiveMapper->insert($collective);

		// Read in collectiveInfo object
		$collectiveInfo = new CollectiveInfo($collective, $safeName, true);

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
	 * @throws CircleDoesNotExistException
	 */
	public function trashCollective(string $userId, int $id): CollectiveInfo {
		if (null === $collective = $this->collectiveMapper->findById($id, $userId)) {
			throw new NotFoundException('Collective not found: ' . $id);
		}
		$name = $this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId());

		if (!$this->collectiveMapper->isAdmin($collective, $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to delete collective: ' . $id);
		}

		return new CollectiveInfo($this->collectiveMapper->trash($collective),
			$name,
			true);
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 * @param bool   $deleteCircle
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws CircleDoesNotExistException
	 */
	public function deleteCollective(string $userId, int $id, bool $deleteCircle): CollectiveInfo {
		if (null === $collective = $this->collectiveMapper->findTrashById($id, $userId)) {
			throw new NotFoundException('Collective not found in trash: ' . $id);
		}
		$name = $this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId());

		if ($deleteCircle) {
			Circles::destroyCircle($collective->getCircleUniqueId());
		}

		// Delete collective folder and its contents
		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException | \OCP\Files\NotFoundException | FilesNotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder');
		}

		return new CollectiveInfo($this->collectiveMapper->delete($collective),
			$name,
			true);
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws CircleDoesNotExistException
	 */
	public function restoreCollective(string $userId, int $id): CollectiveInfo {
		if (null === $collective = $this->collectiveMapper->findTrashById($id, $userId)) {
			throw new NotFoundException('Collective not found in trash: ' . $id);
		}
		$name = $this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId());

		return new CollectiveInfo($this->collectiveMapper->restore($collective),
			$name,
			true);
	}
}
