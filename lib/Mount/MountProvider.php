<?php

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Cache;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\App\IAppManager;
use OCP\AppFramework\QueryException;
use OCP\DB\Exception;
use OCP\Files\Config\IMountProvider;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;

class MountProvider implements IMountProvider {
	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

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
	 * @param IMimeTypeLoader         $mimeTypeLoader
	 * @param IAppManager             $appManager
	 * @param UserFolderHelper        $userFolderHelper
	 */
	public function __construct(
		CollectiveHelper $collectiveHelper,
		CollectiveFolderManager $collectiveFolderManager,
		IMimeTypeLoader $mimeTypeLoader,
		IAppManager $appManager,
		UserFolderHelper $userFolderHelper) {
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->mimeTypeLoader = $mimeTypeLoader;
		$this->appManager = $appManager;
		$this->userFolderHelper = $userFolderHelper;
	}

	/**
	 * @param IUser $user
	 *
	 * @return array
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getFoldersForUser(IUser $user): array {
		$folders = [];
		if (!$this->appManager->isEnabledForUser('circles', $user)) {
			return $folders;
		}
		try {
			$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false);
		} catch (QueryException $e) {
			throw new NotFoundException($e->getMessage());
		}
		foreach ($collectiveInfos as $c) {
			$mountPointName = $c->getName();
			try {
				$cacheEntry = $this->collectiveFolderManager->getFolderFileCache($c->getId(), $mountPointName);
			} catch (FilesNotFoundException | Exception $e) {
				throw new NotFoundException($e->getMessage());
			}
			$folders[] = [
				'folder_id' => $c->getId(),
				'mount_point' => $this->userFolderHelper->get($user->getUID())->getName() . '/' . $mountPointName,
				'rootCacheEntry' => (isset($cacheEntry['fileid'])) ? Cache::cacheEntryFromData($cacheEntry, $this->mimeTypeLoader) : null
			];
		}
		return $folders;
	}

	/**
	 * @param IUser           $user
	 * @param IStorageFactory $loader
	 *
	 * @return IMountPoint[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$folders = $this->getFoldersForUser($user);

		try {
			return array_map(function ($folder) use ($user, $loader) {
				return $this->collectiveFolderManager->getMount(
					$folder['folder_id'],
					'/' . $user->getUID() . '/files/' . $folder['mount_point'],
					$folder['rootCacheEntry'],
					$loader,
					$user
				);
			}, $folders);
		} catch (FilesNotFoundException | \Exception $e) {
			throw new NotFoundException($e->getMessage());
		}
	}
}
