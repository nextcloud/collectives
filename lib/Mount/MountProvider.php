<?php

namespace OCA\Collectives\Mount;

use OC\Files\Storage\Wrapper\Jail;
use OCA\Collectives\Service\CollectiveHelper;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IUserSession;

class MountProvider implements IMountProvider {
	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/** @var IUserSession */
	private $userSession;

	/**
	 * MountProvider constructor.
	 *
	 * @param CollectiveHelper   $collectiveHelper
	 * @param CollectiveFolderManager $collectiveFolderManager
	 * @param IUserSession             $userSession
	 */
	public function __construct(
		CollectiveHelper $collectiveHelper,
		CollectiveFolderManager $collectiveFolderManager,
		IUserSession $userSession) {
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->userSession = $userSession;
	}

	/**
	 * @param IUser $user
	 *
	 * @return array
	 */
	public function getFoldersForUser(IUser $user): array {
		// TODO: Search filecache ID, see joinQueryWithFileCache() from groupfolders app
		$collectives = $this->collectiveHelper->getCollectivesForUser($user->getUID());
		$folders = [];
		foreach ($collectives as $c) {
			$folders[] = ['folderId' => $c->getId(),
				'mountPoint' => $c->getName()];
		}
		return $folders;
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$folders = $this->getFoldersForUser($user);

		// TODO: Create '/Collectives' subfolder in user home and use it

		return array_map(function ($folder) use ($user, $loader) {
			return $this->getMount(
				$folder['folderId'],
				'/' . $user->getUID() . '/files/' . $folder['mountPoint'],
				$folder['rootCacheEntry'],
				$loader,
				$user
			);
		}, $folders);
	}

	/**
	 * @param int                  $id
	 * @param string               $mountPoint
	 * @param null                 $cacheEntry
	 * @param IStorageFactory|null $loader
	 * @param IUser|null           $user
	 *
	 * @return IMountPoint
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getMount(int $id,
							 string $mountPoint,
							 $cacheEntry = null,
							 IStorageFactory $loader = null,
							 IUser $user = null): IMountPoint {
		if (!$cacheEntry) {
			// trigger folder creation
			$this->collectiveFolderManager->getFolder($id);
		}

		$storage = $this->collectiveFolderManager->getRootFolder()->getStorage();

		$rootPath = $this->getJailPath($id);

		$baseStorage = new Jail([
			'storage' => $storage,
			'root' => $rootPath
		]);
		$collectiveStorage = new CollectiveStorage([
			'storage' => $baseStorage,
			'folderId' => $id,
			'rootCacheEntry' => $cacheEntry,
			'userSession' => $this->userSession,
			'mountOwner' => $user,
		]);

		return new CollectiveMountPoint(
			$id,
			$this->collectiveFolderManager,
			$collectiveStorage,
			$mountPoint,
			null,
			$loader
		);
	}

	/**
	 * @param int $folderId
	 *
	 * @return string
	 */
	public function getJailPath(int $folderId): string {
		return $this->collectiveFolderManager->getRootFolder()->getInternalPath() . '/' . $folderId;
	}
}
