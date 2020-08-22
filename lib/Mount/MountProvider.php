<?php

namespace OCA\Collectives\Mount;

use OC\Files\Node\LazyFolder;
use OC\Files\Storage\Wrapper\Jail;
use OCA\Collectives\Service\CollectiveHelper;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IUserSession;

class MountProvider implements IMountProvider {
	private const LANDING_PAGE = 'README.md';

	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var CollectiveRootPathHelper */
	private $collectiveRootPathHelper;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserSession */
	private $userSession;

	/**
	 * MountProvider constructor.
	 *
	 * @param CollectiveHelper   $collectiveHelper
	 * @param CollectiveRootPathHelper $collectiveRootPathHelper
	 * @param IRootFolder              $rootFolder
	 * @param IUserSession             $userSession
	 */
	public function __construct(
		CollectiveHelper $collectiveHelper,
		CollectiveRootPathHelper $collectiveRootPathHelper,
		IRootFolder $rootFolder,
		IUserSession $userSession) {
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveRootPathHelper = $collectiveRootPathHelper;
		$this->rootFolder = $rootFolder;
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
	 * @return string|null
	 */
	private function getCurrentUID(): ?string {
		$user = $this->userSession->getUser();
		return $user ? $user->getUID() : null;
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
			$this->getFolder($id);
		}

		$storage = $this->getCollectivesRootFolder()->getStorage();

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
			$this->collectiveRootPathHelper,
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
		return $this->getCollectivesRootFolder()->getInternalPath() . '/' . $folderId;
	}

	/**
	 * @return Folder
	 */
	private function getCollectivesRootFolder(): Folder {
		$rootFolder = $this->rootFolder;
		return (new LazyFolder(function () use ($rootFolder) {
			try {
				return $rootFolder->get($this->collectiveRootPathHelper->get());
			} catch (NotFoundException $e) {
				return $rootFolder->newFolder($this->collectiveRootPathHelper->get());
			}
		}));
	}

	/**
	 * @param int  $id
	 * @param bool $create
	 *
	 * @return Folder|null
	 * @throws NotPermittedException
	 */
	public function getFolder(int $id, bool $create = true): ?Folder {
		try {
			$folder = $this->getCollectivesRootFolder()->get((string)$id);
			if (!$folder instanceof Folder) {
				return null;
			}
		} catch (NotFoundException $e) {
			if ($create) {
				$folder = $this->getCollectivesRootFolder()->newFolder((string)$id);
			}
			return null;
		}

		return $folder;
	}
}
