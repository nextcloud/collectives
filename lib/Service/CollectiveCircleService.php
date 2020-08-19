<?php

namespace OCA\Unite\Service;

use OC\User\NoUserException;
use OCA\Circles\Api\v1\Circles;
use OCA\Unite\Db\Collective;
use OCA\Unite\Db\CollectiveMapper;
use OCA\Unite\Fs\NodeHelper;
use OCA\Unite\Model\CollectiveInfo;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\QueryException;
use OCP\Constants;
use OCP\Files\AlreadyExistsException;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\IManager;
use OCP\Share\IShare;

class CollectiveCircleService {
	/** @var IRootFolder */
	private $root;
	/** @var CollectiveMapper */
	private $collectiveMapper;
	/** @var CollectiveCircleHelper */
	private $collectiveCircleHelper;
	/** @var NodeHelper */
	private $nodeHelper;
	/** @var IManager */
	private $shareManager;

	/**
	 * CollectiveCircleService constructor.
	 *
	 * @param IRootFolder            $root
	 * @param CollectiveMapper       $collectiveMapper
	 * @param CollectiveCircleHelper $collectiveCircleHelper
	 * @param NodeHelper             $nodeHelper
	 * @param IManager               $shareManager
	 */
	public function __construct(
		IRootFolder $root,
		CollectiveMapper $collectiveMapper,
		CollectiveCircleHelper $collectiveCircleHelper,
		NodeHelper $nodeHelper,
		IManager $shareManager
	) {
		$this->root = $root;
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveCircleHelper = $collectiveCircleHelper;
		$this->nodeHelper = $nodeHelper;
		$this->shareManager = $shareManager;
	}

	/**
	 * @param string $userId
	 *
	 * @return array
	 * @throws QueryException
	 * @throws NotFoundException
	 */
	public function getCollectives(string $userId): array {
		$collectives = $this->collectiveCircleHelper->getCollectivesForUser($userId);
		$cis = [];
		foreach ($collectives as $c) {
			$ci = new CollectiveInfo();
			$ci->fromCollective($c, $this->collectiveMapper->getCollectiveFolder($c->getId()));
			$cis[] = $ci;
		}
		return $cis;
	}

	/**
	 * @param string $userId
	 * @param string $name
	 *
	 * @return CollectiveInfo
	 * @throws AlreadyExistsException
	 * @throws NotPermittedException
	 * @throws OCSException
	 * @throws NoUserException
	 */
	public function createCollective(string $userId, string $name): CollectiveInfo {
		if (empty($name)) {
			throw new \RuntimeException('Empty collective name is not allowed');
		}

		// TODO: Create a hidden CollectiveCircle user

		// Create a new folder for the collective
		$userFolder = $this->root->getUserFolder($userId);
		$safeName = $this->nodeHelper->sanitiseFilename($name);
		$collectivePath = NodeHelper::generateFilename($userFolder, $safeName);

		$folder = $userFolder->newFolder($collectivePath);
		if (!($folder instanceof Folder)) {
			throw new \RuntimeException($collectivePath . ' is not a folder');
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
		$collective->setFolderId($folder->getId());
		$collective->setOwnerId($userId);
		$this->collectiveMapper->insert($collective);

		$ci = new CollectiveInfo();
		$ci->fromCollective($collective, $folder);

		// Share folder with circle
		$share = $this->shareManager->newShare();
		$share->setNode($folder);
		try {
			$folder->lock(ILockingProvider::LOCK_SHARED);
		} catch (LockedException $e) {
			throw new OCSException('Failed to create lock for ' . $folder->getName() . ': ' . $e->getMessage());
		}
		$share->setSharedWith($circle->getUniqueId());
		$share->setPermissions(Constants::PERMISSION_ALL);
		$share->setShareType(IShare::TYPE_CIRCLE);
		$share->setSharedBy($userId);


		try {
			$this->shareManager->createShare($share);
		} catch (\Exception $e) {
			throw new OCSException('Failed to create share for ' . $folder->getName() . ': ' . $e->getMessage());
		} finally {
			$folder->unlock(ILockingProvider::LOCK_SHARED);
		}

		return $ci;
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 */
	public function deleteCollective(string $userId, int $id): CollectiveInfo {
		$this->collectiveCircleHelper->userHasCollective($userId, $id);
		if (null === $collective = $this->collectiveMapper->findById($id)) {
			throw new NotFoundException('Failed to delete collective, not found: ' . $id);
		}

		$folder = $this->collectiveMapper->getCollectiveFolder($collective->getId());

		try {
			$circle = Circles::detailsCircle($collective->getCircleUniqueId());
			Circles::destroyCircle($collective->getCircleUniqueId());
		} catch (QueryException $e) {
			throw new NotFoundException('Failed to delete collective (circle not deleted): ' . $id);
		}

		$collective = $this->collectiveMapper->delete($collective);
		try {
			$folder->delete();
		} catch (InvalidPathException | \OCP\Files\NotFoundException | NotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder: ' . $id);
		}

		$ci = new CollectiveInfo();
		$ci->fromCollective($collective);

		return $ci;
	}
}
