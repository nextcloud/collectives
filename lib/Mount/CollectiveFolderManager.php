<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Mount;

use Exception;
use OC;
use OC\Files\Node\LazyFolder;
use OC\Files\Storage\Wrapper\Jail;
use OC\Files\Storage\Wrapper\PermissionsMask;
use OCA\Collectives\ACL\ACLStorageWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountPoint;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorageFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;

class CollectiveFolderManager {
	private const SKELETON_DIR = 'skeleton';
	private const LANDING_PAGE_TITLE = 'Readme';
	private const SUFFIX = '.md';

	private ?string $rootPath = null;
	private ?int $rootFolderId = null;
	private ?Folder $collectivesRootFolder = null;

	public function __construct(
		private IRootFolder $rootFolder,
		private IDBConnection $connection,
		private IConfig $config,
		private IUserSession $userSession,
		private IRequest $request,
	) {
	}

	private function getAppdataPath(): string {
		$instanceId = $this->config->getSystemValueString('instanceid');
		return 'appdata_' . $instanceId;
	}

	public function getRootPath(): string {
		if ($this->rootPath !== null) {
			return $this->rootPath;
		}

		$this->rootPath = $this->getAppdataPath() . '/collectives';
		return $this->rootPath;
	}

	public function getRootFolder(): Folder {
		if ($this->collectivesRootFolder !== null) {
			return $this->collectivesRootFolder;
		}

		$rootFolder = $this->rootFolder;
		$appdataPath = $this->getAppdataPath();
		$this->collectivesRootFolder = new LazyFolder($rootFolder, function () use ($rootFolder, $appdataPath) {
			/** @var Folder $appdata */
			$appdata = $rootFolder->get($appdataPath);
			try {
				return $appdata->get('/collectives');
			} catch (NotFoundException) {
				return $appdata->newFolder('collectives');
			}
		}, [
			'path' => '/' . $this->getRootPath(),
		]);

		return $this->collectivesRootFolder;
	}

	private function getCurrentUID(): ?string {
		try {
			// wopi requests are not logged in, instead we need to get the editor user from the access token
			if (strpos($this->request->getRawPathInfo(), 'apps/richdocuments/wopi') && class_exists('OCA\Richdocuments\Db\WopiMapper')) {
				$wopiMapper = OC::$server->query('OCA\Richdocuments\Db\WopiMapper');
				$token = $this->request->getParam('access_token');
				if ($token) {
					return $wopiMapper->getPathForToken($token)->getEditorUid();
				}
			}
		} catch (Exception) {
		}

		return $this->userSession->getUser()?->getUID();
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getMount(int $id,
		string $mountPoint,
		int $permissions,
		?ICacheEntry $cacheEntry = null,
		?IStorageFactory $loader = null,
		?IUser $user = null): ?IMountPoint {
		if (!$cacheEntry) {
			try {
				$folder = $this->getOrCreateFolder($id);
			} catch (InvalidPathException|NotPermittedException) {
				return null;
			}
			$cacheEntry = $this->getRootFolder()->getStorage()->getCache()->get($folder->getId());
			if ($cacheEntry === false) {
				return null;
			}
		}

		$storage = new NoExcludePropagatorStorageWrapper(['storage' => $this->getRootFolder()->getStorage()]);

		$rootPath = $this->getJailPath($id);

		// apply acl before jail
		if ($user) {
			$inShare = !OC::$CLI && ($this->getCurrentUID() === null || $this->getCurrentUID() !== $user->getUID());
			$storage = new ACLStorageWrapper([
				'storage' => $storage,
				'permissions' => $permissions,
				'in_share' => $inShare
			]);
			$cacheEntry['permissions'] &= $permissions;
		}

		$baseStorage = new Jail([
			'storage' => $storage,
			'root' => $rootPath
		]);
		$collectiveStorage = new CollectiveStorage([
			'storage' => $baseStorage,
			'folder_id' => $id,
			'rootCacheEntry' => $cacheEntry,
			'mountOwner' => $user
		]);
		$maskedStorage = new PermissionsMask([
			'storage' => $collectiveStorage,
			'mask' => $permissions
		]);

		return new CollectiveMountPoint(
			$id,
			$this,
			$maskedStorage,
			$mountPoint,
			null,
			$loader
		);
	}


	private function getJailPath(int $folderId): string {
		return $this->getRootFolder()->getInternalPath() . '/' . $folderId;
	}

	/**
	 * @throws NotFoundException
	 */
	private function getRootFolderId(): int {
		if ($this->rootFolderId === null) {
			$qb = $this->connection->getQueryBuilder();

			$qb->select('f.fileid')
				->from('filecache', 'f')
				->where($qb->expr()->eq('storage', $qb->createNamedParameter($this->getRootFolderStorageId())))
				->andWhere($qb->expr()->eq('path_hash', $qb->createNamedParameter(md5($this->getRootPath()))));

			$this->rootFolderId = (int)$qb->executeQuery()->fetchColumn();
		}

		return $this->rootFolderId;
	}

	private function getRootFolderStorageId(): int {
		return $this->getRootFolder()->getStorage()->getCache()->getNumericStorageId();
	}

	/**
	 * @throws NotPermittedException
	 */
	private function getSkeletonFolder(Folder $folder): Folder {
		try {
			$skeletonFolder = $folder->get(self::SKELETON_DIR);
			if (!$skeletonFolder instanceof Folder) {
				throw new NotFoundException('Not a folder: ' . $skeletonFolder->getPath());
			}
		} catch (NotFoundException) {
			$skeletonFolder = $folder->newFolder(self::SKELETON_DIR);
		}

		return $skeletonFolder;
	}

	public function getLandingPagePath(string $path, string $lang): string {
		$landingPagePathEnglish = $path . '/' . self::LANDING_PAGE_TITLE . '.en' . self::SUFFIX;
		$landingPagePathLocalized = $path . '/' . self::LANDING_PAGE_TITLE . '.' . $lang . self::SUFFIX;

		return file_exists($landingPagePathLocalized) ? $landingPagePathLocalized : $landingPagePathEnglish;
	}

	/**
	 * @throws NotFoundException
	 * @throws \OCP\DB\Exception
	 */
	public function getFolderFileCache(int $id, string $name): array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select(
			'co.id AS folder_id', 'fc.fileid', 'fc.storage', 'fc.path', 'fc.name AS name',
			'fc.mimetype', 'fc.mimepart', 'fc.size', 'fc.mtime', 'fc.storage_mtime', 'fc.etag', 'fc.encrypted', 'fc.parent', 'fc.permissions AS permissions')
			->from('collectives', 'co')
			->leftJoin('co', 'filecache', 'fc', $qb->expr()->eq('fc.name', $qb->expr()->castColumn('co.id', IQueryBuilder::PARAM_STR)))
			->where($qb->expr()->eq('co.id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('fc.parent', $qb->createNamedParameter($this->getRootFolderId())))
			->andWhere($qb->expr()->eq('fc.storage', $qb->createNamedParameter($this->getRootFolderStorageId(), IQueryBuilder::PARAM_INT)));
		$cache = $qb->executeQuery()->fetch();
		$cache['mount_point'] = $name;
		return $cache;
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function getFolder(int $id): Folder {
		$folder = $this->getRootFolder()->get((string)$id);
		if (!$folder instanceof Folder) {
			throw new InvalidPathException('Not a folder: ' . $folder->getPath());
		}
		return $folder;
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function getOrCreateFolder(int $id): Folder {
		try {
			$folder = $this->getFolder($id);
		} catch (NotFoundException) {
			/** @var Folder $folder */
			$folder = $this->getSkeletonFolder($this->getRootFolder())
				->copy($this->getRootFolder()->getPath() . '/' . $id);
		}

		return $folder;
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function initializeFolder(int $id, string $lang): Folder {
		$folder = $this->getOrCreateFolder($id);

		$landingPageFileName = self::LANDING_PAGE_TITLE . self::SUFFIX;
		if (!$folder->nodeExists($landingPageFileName)) {
			$landingPageDir = __DIR__ . '/../../' . self::SKELETON_DIR;
			$landingPagePath = $this->getLandingPagePath($landingPageDir, $lang);
			if (false !== $content = file_get_contents($landingPagePath)) {
				$folder->newFile($landingPageFileName, $content);
			}
		}

		return $folder;
	}
}
