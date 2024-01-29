<?php

declare(strict_types=1);

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
	public function __construct(
		private CollectiveHelper $collectiveHelper,
		private CollectiveFolderManager $collectiveFolderManager,
		private IMimeTypeLoader $mimeTypeLoader,
		private IAppManager $appManager,
		private LoggerInterface $logger,
		private UserFolderHelper $userFolderHelper) {
	}

	public function getFoldersForUser(IUser $user): array {
		$folders = [];

		try {
			$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($user->getUID(), true, false);
		} catch (QueryException | MissingDependencyException | NotFoundException | NotPermittedException $e) {
			$this->log($e);
			return $folders;
		}

		// Stop here if no collectives were found
		if ($collectiveInfos === []) {
			return $folders;
		}

		try {
			$userFolder = $this->userFolderHelper->get($user->getUID());
		} catch (NotPermittedException $e) {
			$this->log($e);
			return $folders;
		}

		foreach ($collectiveInfos as $c) {
			$mountPointPath = ($this->userFolderHelper->getUserFolderSetting($user->getUID()) === '/')
				? ''
				: $userFolder->getName() . '/';
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
				'mount_point' => $mountPointPath . $mountPointName,
				'permissions' => $c->getUserPermissions(),
				'rootCacheEntry' => (isset($cacheEntry['fileid'])) ? Cache::cacheEntryFromData($cacheEntry, $this->mimeTypeLoader) : null
			];
		}
		return $folders;
	}

	/**
	 * @return IMountPoint[]|null[]
	 */
	public function getMountsForUser(IUser $user, IStorageFactory $loader): array {
		if (!$this->isEnabledForUser($user)) {
			return [];
		}

		$folders = $this->getFoldersForUser($user);
		try {
			return array_map(fn ($folder) => $this->collectiveFolderManager->getMount(
				$folder['folder_id'],
				'/' . $user->getUID() . '/files/' . $folder['mount_point'],
				$folder['permissions'],
				$folder['rootCacheEntry'],
				$loader,
				$user
			), $folders);
		} catch (FilesNotFoundException | \Exception $e) {
			$this->log($e);
			return [];
		}
	}

	protected function isEnabledForUser(IUser $user): bool {
		return $this->appManager->isEnabledForUser('circles', $user)
			&& $this->appManager->isEnabledForUser('collectives', $user);
	}

	protected function log(\Exception $e): void {
		$this->logger->error('Collectives App Error: ' . $e->getMessage(),
			['exception' => $e]
		);
	}
}
