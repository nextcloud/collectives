<?php

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Cache;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCP\App\IAppManager;
use OCP\AppFramework\QueryException;
use OCP\Files\Config\IMountProvider;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
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

	/** @var NodeHelper */
	private $nodeHelper;

	/**
	 * MountProvider constructor.
	 *
	 * @param CollectiveHelper        $collectiveHelper
	 * @param CollectiveFolderManager $collectiveFolderManager
	 * @param IMimeTypeLoader         $mimeTypeLoader
	 * @param IAppManager             $appManager
	 * @param UserFolderHelper        $userFolderHelper
	 * @param NodeHelper              $nodeHelper
	 */
	public function __construct(
		CollectiveHelper $collectiveHelper,
		CollectiveFolderManager $collectiveFolderManager,
		IMimeTypeLoader $mimeTypeLoader,
		IAppManager $appManager,
		UserFolderHelper $userFolderHelper,
		NodeHelper $nodeHelper) {
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->mimeTypeLoader = $mimeTypeLoader;
		$this->appManager = $appManager;
		$this->userFolderHelper = $userFolderHelper;
		$this->nodeHelper = $nodeHelper;
	}

	/**
	 * @param IUser $user
	 *
	 * @return array
	 * @throws NotFoundException
	 * @throws QueryException
	 */
	public function getFoldersForUser(IUser $user): array {
		$folders = [];
		if (!$this->appManager->isEnabledForUser('circles', $user)) {
			return $folders;
		}
		$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false);
		foreach ($collectiveInfos as $c) {
			$cacheEntry = $this->collectiveFolderManager->getFolderFileCache($c->getId());
			$mountPointName = $this->nodeHelper->sanitiseFilename($c->getName());
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
	 */
	public function getMountsForUser(IUser $user, IStorageFactory $loader) {
		$folders = $this->getFoldersForUser($user);

		return array_map(function ($folder) use ($user, $loader) {
			return $this->collectiveFolderManager->getMount(
				$folder['folder_id'],
				'/' . $user->getUID() . '/files/' . $folder['mount_point'],
				$folder['rootCacheEntry'],
				$loader,
				$user
			);
		}, $folders);
	}
}
