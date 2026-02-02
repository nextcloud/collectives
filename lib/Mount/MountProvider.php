<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use OC\Files\Cache\Cache;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\App\IAppManager;
use OCP\AppFramework\QueryException;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class MountProvider implements IMountProvider {
	public function __construct(
		private CollectiveHelper $collectiveHelper,
		private CollectiveFolderManager $collectiveFolderManager,
		private IMimeTypeLoader $mimeTypeLoader,
		private IAppManager $appManager,
		private LoggerInterface $logger,
		private UserFolderHelper $userFolderHelper,
		private IUserSession $userSession,
	) {
	}

	public function getFoldersForUser(IUser $user): array {
		$folders = [];

		try {
			$collectives = $this->collectiveHelper->getCollectivesForUser($user->getUID(), true, false);
		} catch (QueryException|MissingDependencyException|NotFoundException|NotPermittedException $e) {
			$this->log($e);
			return $folders;
		}

		// Stop here if no collectives were found
		if ($collectives === []) {
			return $folders;
		}

		$userFolderSetting = $this->userFolderHelper->getUserFolderSetting($user->getUID());
		$mountPointPath = ($userFolderSetting === DIRECTORY_SEPARATOR)
			? ''
			: $userFolderSetting . DIRECTORY_SEPARATOR;

		$isShare = $this->userSession->getUser() === null;
		$collectiveIds = array_map(fn (Collective $collective): int => $collective->getId(), $collectives);
		$cacheEntryPerCollectiveId = $this->collectiveFolderManager->getFolderFileCachePerCollectiveId($collectiveIds);
		foreach ($collectives as $collective) {
			$cacheEntry = $cacheEntryPerCollectiveId[$collective->getId()] ?? null;
			if ($cacheEntry === null) {
				$this->logger->warning('Could not find cache entry for collective ' . $collective->getId());
				continue;
			}
			$cacheEntry['mount_point'] = $collective->getName();
			$folders[] = [
				'folder_id' => $collective->getId(),
				'mount_point' => $mountPointPath . $collective->getName(),
				'permissions' => $collective->getUserPermissions($isShare),
				'rootCacheEntry' => (isset($cacheEntry['fileid'])) ? Cache::cacheEntryFromData($cacheEntry, $this->mimeTypeLoader) : null
			];
		}

		return $folders;
	}

	public function getMountsForUser(IUser $user, IStorageFactory $loader): array {
		if (!$this->isEnabledForUser($user)) {
			return [];
		}

		$folders = $this->getFoldersForUser($user);

		// If there are no collectives, don't create any mounts
		if (empty($folders)) {
			return [];
		}

		$mounts = [];

		try {
			// Get user folder setting to determine user mount point path
			$userFolderSetting = $this->userFolderHelper->getUserFolderSetting($user->getUID());

			// Delete or rename existing node to avoid conflicts
			$userRootFolder = $this->userFolderHelper->getUserRootFolder($user->getUID());
			if ($userRootFolder->nodeExists($userFolderSetting)) {
				$node = $userRootFolder->get($userFolderSetting);
				if ($node instanceof Folder && count($node->getDirectoryListing()) === 0) {
					// Delete empty folder
					$node->delete();
				} else {
					// Rename node
					$newNodeName = NodeHelper::generateFilename($userRootFolder, $userFolderSetting);
					$node->move($userRootFolder->getPath() . DIRECTORY_SEPARATOR . $newNodeName);
				}
			}

			// Create the collectives root mount point with empty storage
			// The empty storage ensures only mount points exist here, no actual files
			$userMountPoint = '/' . $user->getUID() . '/files' . $userFolderSetting;

			$mounts[] = new CollectivesUserMountPoint(
				$userMountPoint,
				null,
				$loader
			);

			// Create mount points for individual collectives
			$collectiveMounts = array_filter(array_map(fn ($folder): ?IMountPoint => $this->collectiveFolderManager->getMount(
				$folder['folder_id'],
				'/' . $user->getUID() . '/files/' . $folder['mount_point'],
				$folder['permissions'],
				$folder['rootCacheEntry'],
				$loader,
				$user
			), $folders));

			$mounts = array_merge($mounts, $collectiveMounts);

			return $mounts;
		} catch (FilesNotFoundException|\Exception $e) {
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
