<?php

namespace OCA\Collectives\Mount;

use OC\Files\Node\LazyFolder;
use OC\Files\Storage\Wrapper\Jail;
use OCA\Collectives\Service\CollectiveHelper;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IUserSession;

class MountProvider implements IMountProvider {
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

	public function getMount($id, string $mountPoint, $cacheEntry = null, IStorageFactory $loader = null, IUser $user = null): IMountPoint {
		if (!$cacheEntry) {
			// trigger folder creation
			$this->getFolder($id);
		}

		$storage = $this->getCollectivesRootFolder()->getStorage();

		$rootPath = $this->getJailPath((int)$id);

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
	 * @param      $id
	 * @param bool $create
	 *
	 * @return Node|null
	 */
	public function getFolder($id, bool $create = true): ?Node {
		try {
			return $this->getCollectivesRootFolder()->get((string)$id);
		} catch (NotFoundException $e) {
			if ($create) {
				return $this->getCollectivesRootFolder()->newFolder((string)$id);
			}
			return null;
		}
	}
}
