<?php

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Cache;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\MissingDependencyException;
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
use Psr\Log\LoggerInterface;

class MountProvider implements IMountProvider {
	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/** @var LoggerInterface */
	private $logger;

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
	 * @param LoggerInterface         $logger
	 * @param UserFolderHelper        $userFolderHelper
	 */
	public function __construct(
		CollectiveHelper $collectiveHelper,
		CollectiveFolderManager $collectiveFolderManager,
		IMimeTypeLoader $mimeTypeLoader,
		IAppManager $appManager,
		LoggerInterface $logger,
		UserFolderHelper $userFolderHelper) {
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->mimeTypeLoader = $mimeTypeLoader;
		$this->appManager = $appManager;
		$this->logger = $logger;
		$this->userFolderHelper = $userFolderHelper;
	}

	/**
	 * @param IUser $user
	 *
	 * @return array
	 */
	public function getFoldersForUser(IUser $user): array {
		$folders = [];

		try {
			$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($user->getUID());
		} catch (QueryException | MissingDependencyException | NotFoundException | NotPermittedException $e) {
			$this->log($e);
			return $folders;
		}

		// Stop here if no collectives were found
		if (empty($collectiveInfos)) {
			return $folders;
		}

		try {
			$userFolder = $this->userFolderHelper->get($user->getUID());
		} catch (NotPermittedException $e) {
			$this->log($e);
			return $folders;
		}

		foreach ($collectiveInfos as $c) {
			$mountPointName = $c->getName();
			try {
				$cacheEntry = $this->collectiveFolderManager->getFolderFileCache($c->getId(), $mountPointName);
			} catch (FilesNotFoundException | Exception $e) {
				$this->log($e);
				// maybe some other caches can be found.
				continue;
			}
			$folders[] = [
				'folder_id' => $c->getId(),
				'mount_point' => $userFolder->getName() . '/' . $mountPointName,
				'permissions' => $c->getUserPermissions(),
				'rootCacheEntry' => (isset($cacheEntry['fileid'])) ? Cache::cacheEntryFromData($cacheEntry, $this->mimeTypeLoader) : null
			];
		}
		return $folders;
	}

	/**
	 * @param IUser           $user
	 * @param IStorageFactory $loader
	 *
	 * @return IMountPoint[]|null[]
	 */
	public function getMountsForUser(IUser $user, IStorageFactory $loader): array {
		if (!$this->isEnabledForUser($user)) {
			return [];
		}

		$folders = $this->getFoldersForUser($user);
		try {
			return array_map(function ($folder) use ($user, $loader) {
				return $this->collectiveFolderManager->getMount(
					$folder['folder_id'],
					'/' . $user->getUID() . '/files/' . $folder['mount_point'],
					$folder['permissions'],
					$folder['rootCacheEntry'],
					$loader,
					$user
				);
			}, $folders);
		} catch (FilesNotFoundException | \Exception $e) {
			$this->log($e);
			return [];
		}
	}

	/**
	 * @param IUser $user
	 *
	 * @return bool
	 */
	protected function isEnabledForUser(IUser $user): bool {
		return $this->appManager->isEnabledForUser('circles', $user)
			&& $this->appManager->isEnabledForUser('collectives', $user)
			&& $user->getQuota() !== '0 B';
	}

	/**
	 * @param \Exception $e
	 */
	protected function log(\Exception $e): void {
		$this->logger->error('Collectives App Error: ' . $e->getMessage(),
			['exception' => $e]
		);
	}
}
