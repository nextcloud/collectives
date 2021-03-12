<?php

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Cache;
use OC\Files\Storage\Wrapper\Jail;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCP\App\IAppManager;
use OCP\Files\Config\IMountProvider;
use OCP\Files\IMimeTypeLoader;
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

	/** @var IMimeTypeLoader */
	private $mimeTypeLoader;

	/** @var IAppManager */
	private $appManager;

	/** @var UserFolderHelper */
	private $userFolderHelper;

	/**
	 * MountProvider constructor.
	 *
	 * @param CollectiveHelper        $collectiveHelper
	 * @param CollectiveFolderManager $collectiveFolderManager
	 * @param IUserSession            $userSession
	 * @param IMimeTypeLoader         $mimeTypeLoader
	 * @param IAppManager             $appManager
	 * @param UserFolderHelper        $userFolderHelper
	 */
	public function __construct(
		CollectiveHelper $collectiveHelper,
		CollectiveFolderManager $collectiveFolderManager,
		IUserSession $userSession,
		IMimeTypeLoader $mimeTypeLoader,
		IAppManager $appManager,
		UserFolderHelper $userFolderHelper) {
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->userSession = $userSession;
		$this->mimeTypeLoader = $mimeTypeLoader;
		$this->appManager = $appManager;
		$this->userFolderHelper = $userFolderHelper;
	}

	/**
	 * @param IUser $user
	 *
	 * @return array
	 */
	public function getFoldersForUser(IUser $user): array {
		$folders = [];
		if (!$this->appManager->isEnabledForUser('circles', $user)) {
			return $folders;
		}
		$collectives = $this->collectiveHelper->getCollectivesForUser($user->getUID());
		foreach ($collectives as $c) {
			$cacheEntry = $this->collectiveFolderManager->getFolderFileCache($c->getId());
			$folders[] = [
				'folder_id' => $c->getId(),
				'mount_point' => $this->userFolderHelper->getName($user->getUID()) . '/' . $c->getName(),
				'rootCacheEntry' => (isset($cacheEntry['fileid'])) ? Cache::cacheEntryFromData($cacheEntry, $this->mimeTypeLoader) : null
			];
		}
		return $folders;
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$folders = $this->getFoldersForUser($user);

		return array_map(function ($folder) use ($user, $loader) {
			return $this->getMount(
				$folder['folder_id'],
				'/' . $user->getUID() . '/files/' . $folder['mount_point'],
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
			'folder_id' => $id,
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
