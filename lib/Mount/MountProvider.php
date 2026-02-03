<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\Storage\IStorage;
use OCP\Files\Storage\IStorageFactory;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class MountProvider implements IMountProvider {
	public function __construct(
		private CollectiveHelper $collectiveHelper,
		private CollectiveFolderManager $collectiveFolderManager,
		private IDBConnection $connection,
		private IMountProviderCollection $mountProviderCollection,
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

		foreach ($collectives as $c) {
			$mountPointName = $c->getName();
			try {
				$cacheEntry = $this->collectiveFolderManager->getFolderFileCache($c->getId(), $mountPointName);
			} catch (FilesNotFoundException|Exception $e) {
				$this->log($e);
				// maybe some other caches can be found.
				continue;
			}
			$isShare = $this->userSession->getUser() === null;
			$folders[] = [
				'folder_id' => $c->getId(),
				'mount_point' => $mountPointPath . $mountPointName,
				'permissions' => $c->getUserPermissions($isShare),
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
			$this->resolveNameConflict($user, trim($userFolderSetting, '/'));

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

	private function isEnabledForUser(IUser $user): bool {
		return $this->appManager->isEnabledForUser('circles', $user)
			&& $this->appManager->isEnabledForUser('collectives', $user);
	}

	private function log(\Exception $e): void {
		$this->logger->error('Collectives App Error: ' . $e->getMessage(),
			['exception' => $e]
		);
	}

	private function resolveNameConflict(IUser $user, string $path): void {
		$userHome = $this->mountProviderCollection->getHomeMountForUser($user);
		$filesPath = 'files/' . $path;
		$filesPathHash = md5($filesPath);

		$query = $this->connection->getQueryBuilder();
		$query->select('path')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createNamedParameter($userHome->getNumericStorageId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('path_hash', $query->createNamedParameter($filesPathHash, IQueryBuilder::PARAM_STR)));

		if ($query->executeQuery()->fetchOne() === false) {
			// No conflicting entry found
			return;
		};

		/** @var IStorage $userHomeStorage */
		$userHomeStorage = $userHome->getStorage();
		$userHomeCache = $userHomeStorage->getCache();
		$userFolderCache = $userHomeCache->get($filesPath);

		// Check if node is a folder and empty
		$isEmptyDir = false;
		if ($userFolderCache && $userFolderCache['mimetype'] === 'httpd/unix-directory') {
			$query = $this->connection->getQueryBuilder();
			$query->select('path')
				->from('filecache')
				->where($query->expr()->eq('storage', $query->createNamedParameter($userHome->getNumericStorageId(), IQueryBuilder::PARAM_INT)))
				->andWhere($query->expr()->like('path', $query->createNamedParameter($filesPath . '/%', IQueryBuilder::PARAM_STR)))
				->setMaxResults(1);
			if ($query->executeQuery()->fetchOne() === false) {
				$isEmptyDir = true;
			}
		}

		if ($isEmptyDir) {
			// Delete empty folder
			$userHomeStorage->unlink($filesPath);
			$userHomeCache->remove($filesPath);
			$userHomeStorage->getPropagator()->propagateChange($filesPath, time());
		} else {
			// Rename node
			$i = 1;
			$folderName = $path . ' (' . $i++ . ')';
			while ($userHomeCache->inCache('files/' . $folderName)) {
				$folderName = $path . ' (' . $i++ . ')';
			}

			$userHomeStorage->rename($filesPath, 'files/' . $folderName);
			$userHomeCache->move($filesPath, 'files/' . $folderName);
			$userHomeStorage->getPropagator()->propagateChange('files/' . $folderName, time());
		}
	}
}
