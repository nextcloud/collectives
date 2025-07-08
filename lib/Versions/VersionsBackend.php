<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Versions;

use LogicException;
use OCA\Collectives\Db\CollectiveVersion as CollectiveVersionEntity;
use OCA\Collectives\Db\CollectiveVersionMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Mount\CollectiveMountPoint;
use OCA\Collectives\Mount\CollectiveStorage;
use OCA\Files_Versions\Versions\IDeletableVersionBackend;
use OCA\Files_Versions\Versions\IMetadataVersion;
use OCA\Files_Versions\Versions\IMetadataVersionBackend;
use OCA\Files_Versions\Versions\INeedSyncVersionBackend;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\IVersionsImporterBackend;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

class VersionsBackend implements IVersionBackend, IMetadataVersionBackend, IDeletableVersionBackend, INeedSyncVersionBackend, IVersionsImporterBackend {
	public function __construct(
		private CollectiveFolderManager $collectiveFolderManager,
		private CollectiveVersionMapper $collectiveVersionMapper,
		private IMimeTypeLoader $mimeTypeLoader,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
	}

	private function getAppFolder(): Folder {
		return $this->collectiveFolderManager->getRootFolder();
	}

	public function useBackendForStorage(IStorage $storage): bool {
		return true;
	}

	public function getFolderIdForFile(FileInfo $file): int {
		$storage = $file->getStorage();
		$mountPoint = $file->getMountPoint();

		// getting it from the mountpoint is more efficient
		if ($mountPoint instanceof CollectiveMountPoint) {
			return $mountPoint->getFolderId();
		} elseif ($storage->instanceOfStorage(CollectiveStorage::class)) {
			/** var CollectiveStorage $storage */
			return $storage->getFolderId();
		}
		throw new LogicException('Collective folder version backend called for non Collective folder file');
	}

	public function getVersionFolderForFile(FileInfo $file): Folder {
		$folderId = $this->getFolderIdForFile($file);
		$collectivesVersionsFolder = $this->getVersionsFolder($folderId);

		try {
			/** @var Folder $versionsFolder */
			$versionsFolder = $collectivesVersionsFolder->get((string)$file->getId());

			return $versionsFolder;
		} catch (NotFoundException) {
			// The folder for the file's version might not exist if no versions have been created yet.
			return $collectivesVersionsFolder->newFolder((string)$file->getId());
		}
	}

	/**
	 * @throws InvalidPathException
	 */
	public function getVersionsForFile(IUser $user, FileInfo $file): array {
		$versionsFolder = $this->getVersionFolderForFile($file);

		try {
			$versions = $this->getVersionsForFileFromDb($file, $user);

			// Early exit if we find any version in the database.
			// Else we continue to populate the DB from what's on disk.
			if (count($versions) > 0) {
				return $versions;
			}

			// Insert the entry in the DB for the current version.
			$versionEntity = new CollectiveVersionEntity();
			$versionEntity->setFileId($file->getId());
			$versionEntity->setTimestamp($file->getMTime());
			$versionEntity->setSize($file->getSize());
			$versionEntity->setMimetype($this->mimeTypeLoader->getId($file->getMimetype()));
			$versionEntity->setDecodedMetadata([]);
			$this->collectiveVersionMapper->insert($versionEntity);

			// Insert entries in the DB for existing versions.
			$versionsOnFS = $versionsFolder->getDirectoryListing();
			foreach ($versionsOnFS as $version) {
				if ($version instanceof Folder) {
					$this->logger->error('Found an unexpected subfolder inside the Team folder version folder.');
				}

				$versionEntity = new CollectiveVersionEntity();
				$versionEntity->setFileId($file->getId());
				// HACK: before this commit, versions were created with the current timestamp instead of the version's mtime.
				// This means that the name of some versions is the exact mtime of the next version. This behavior is now fixed.
				// To prevent occasional conflicts between the last version and the current one, we decrement the last version mtime.
				$mtime = (int)$version->getName();
				if ($mtime === $file->getMTime()) {
					$versionEntity->setTimestamp($mtime - 1);
					$version->move($version->getParent()->getPath() . '/' . ($mtime - 1));
				} else {
					$versionEntity->setTimestamp($mtime);
				}

				$versionEntity->setSize($version->getSize());
				// Use the main file mimetype for this initialization as the original mimetype is unknown.
				$versionEntity->setMimetype($this->mimeTypeLoader->getId($file->getMimetype()));
				$versionEntity->setDecodedMetadata([]);
				$this->collectiveVersionMapper->insert($versionEntity);
			}

			return $this->getVersionsForFileFromDb($file, $user);
		} catch (NotFoundException) {
			return [];
		}
	}

	/**
	 * @return IVersion[]
	 */
	private function getVersionsForFileFromDB(FileInfo $fileInfo, IUser $user): array {
		$folderId = $this->getFolderIdForFile($fileInfo);
		$mountPoint = $fileInfo->getMountPoint();
		$versionsFolder = $this->getVersionFolderForFile($fileInfo);
		/** @var Folder $collectiveFolder */
		$collectiveFolder = $this->getVersionsFolder($folderId);

		$versionEntities = $this->collectiveVersionMapper->findAllVersionsForFileId($fileInfo->getId());
		$mappedVersions = array_map(
			function (CollectiveVersionEntity $versionEntity) use ($versionsFolder, $mountPoint, $fileInfo, $user, $folderId, $collectiveFolder) {
				if ($fileInfo->getMtime() === $versionEntity->getTimestamp()) {
					if ($fileInfo instanceof File) {
						$versionFile = $fileInfo;
					} else {
						$versionFile = $collectiveFolder->get($fileInfo->getInternalPath());
					}
				} else {
					try {
						$versionFile = $versionsFolder->get((string)$versionEntity->getTimestamp());
					} catch (NotFoundException) {
						// The version does not exist on disk anymore, so we can delet eits entity the DB.
						// The reality is that the disk version might have been lost during a move operation between storages,
						// and it's not possible to recover it, so removing the entity makes sense.
						$this->collectiveVersionMapper->delete($versionEntity);

						return null;
					}
				}

				return new CollectiveVersion(
					$versionEntity->getTimestamp(),
					$versionEntity->getTimestamp(),
					$fileInfo->getName(),
					$versionEntity->getSize(),
					$this->mimeTypeLoader->getMimetypeById($versionEntity->getMimetype()),
					$mountPoint->getInternalPath($fileInfo->getPath()),
					$fileInfo,
					$this,
					$user,
					$versionEntity->getDecodedMetadata(),
					$versionFile,
					$folderId,
				);
			},
			$versionEntities,
		);
		// Filter out null values.
		return array_filter($mappedVersions);
	}

	/**
	 * @throws NotPermittedException
	 */
	public function createVersion(IUser $user, FileInfo $file): void {
		$versionsFolder = $this->getVersionFolderForFile($file);

		$versionMount = $versionsFolder->getMountPoint();
		$sourceMount = $file->getMountPoint();
		$sourceCache = $sourceMount->getStorage()->getCache();
		$revision = $file->getMtime();

		$versionInternalPath = $versionsFolder->getInternalPath() . '/' . $revision;
		$sourceInternalPath = $file->getInternalPath();

		$versionMount->getStorage()->copyFromStorage($sourceMount->getStorage(), $sourceInternalPath, $versionInternalPath);
		$versionMount->getStorage()->getCache()->copyFromCache($sourceCache, $sourceCache->get($sourceInternalPath), $versionInternalPath);
	}

	/**
	 * @throws NotPermittedException
	 */
	public function rollback(IVersion $version): void {
		if (!($version instanceof CollectiveVersion)) {
			throw new LogicException('Trying to restore a version from a file not in a Collective folder');
		}

		if (!$this->currentUserHasPermissions($version->getSourceFile(), Constants::PERMISSION_UPDATE)) {
			throw new NotPermittedException('Failed to restore version');
		}

		$this->createVersion($version->getUser(), $version->getSourceFile());

		/** @var CollectiveMountPoint $targetMount */
		$targetMount = $version->getSourceFile()->getMountPoint();
		$targetCache = $targetMount->getStorage()->getCache();
		$versionMount = $version->getVersionFile()->getMountPoint();
		$versionCache = $versionMount->getStorage()->getCache();

		$targetInternalPath = $version->getSourceFile()->getInternalPath();
		$versionInternalPath = $version->getVersionFile()->getInternalPath();

		$targetMount->getStorage()->copyFromStorage($versionMount->getStorage(), $versionInternalPath, $targetInternalPath);
		$versionMount->getStorage()->getCache()->copyFromCache($targetCache, $versionCache->get($versionInternalPath), $versionInternalPath);
	}

	/**
	 * @return false|resource
	 * @throws LockedException
	 * @throws NotPermittedException
	 */
	public function read(IVersion $version) {
		if ($version instanceof CollectiveVersion) {
			return $version->getVersionFile()->fopen('r');
		}

		return false;
	}

	public function getVersionFile(IUser $user, FileInfo $sourceFile, $revision): File {
		$versionsFolder = $this->getVersionFolderForFile($sourceFile);
		$file = $versionsFolder->get((string)$revision);
		assert($file instanceof File);

		return $file;
	}

	/**
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	public function getAllVersionedFiles(array $folder): array {
		$versionsFolder = $this->getVersionsFolder($folder['id']);
		$mount = $this->collectiveFolderManager->getMount(
			$folder['id'],
			'/dummyuser/files/Collectives/' . $folder['mount_point'],
			Constants::PERMISSION_ALL
		);
		if ($mount === null) {
			$this->logger->error('Tried to get all the versioned files from a non existing mountpoint');
			return [];
		}
		try {
			$contents = $versionsFolder->getDirectoryListing();
		} catch (NotFoundException) {
			return [];
		}

		$fileIds = array_map(static fn (Node $node): int => (int)$node->getName(), $contents);
		$files = array_map(static function (int $fileId) use ($mount): ?FileInfo {
			$cacheEntry = $mount->getStorage()->getCache()->get($fileId);
			if ($cacheEntry) {
				return new \OC\Files\FileInfo($mount->getMountPoint() . '/' . $cacheEntry->getPath(), $mount->getStorage(), $cacheEntry->getPath(), $cacheEntry, $mount);
			}

			return null;
		}, $fileIds);

		return array_combine($fileIds, $files);
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function deleteAllVersionsForFile(int $folderId, int $fileId): void {
		$versionsFolder = $this->getVersionsFolder($folderId);
		try {
			$versionsFolder->get((string)$fileId)->delete();
			$this->collectiveVersionMapper->deleteAllVersionsForFileId($fileId);
		} catch (NotFoundException) {
		}
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function deleteVersionsFolder(int $folderId): void {
		try {
			/** @var Folder $versionsFolder */
			$versionsFolder = $this->getVersionsFolder($folderId);
			foreach ($versionsFolder->getDirectoryListing() as $fileFolder) {
				if (!($fileFolder instanceof Folder)) {
					continue;
				}
				foreach ($fileFolder->getDirectoryListing() as $file) {
					$this->deleteAllVersionsForFile($folderId, $file->getId());
				}
			}
			$versionsFolder->delete();
		} catch (NotFoundException) {
			// Folder doesn't exist
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getVersionsFolder(int $folderId): Folder {
		try {
			/** @var Folder $folder */
			$folder = $this->getAppFolder()->get('versions/' . $folderId);

			return $folder;
		} catch (NotFoundException) {
			/** @var Folder $versionsFolder */
			$versionsFolder = $this->getAppFolder()->nodeExists('versions') ? $this->getAppFolder()->get('versions') : $this->getAppFolder()->newFolder('versions');

			return $versionsFolder->newFolder((string)$folderId);
		}
	}

	public function setMetadataValue(Node $node, int $revision, string $key, string $value): void {
		if (!$this->currentUserHasPermissions($node, Constants::PERMISSION_UPDATE)) {
			throw new NotPermittedException('Failed to update version\'s metadata');
		}

		$versionEntity = $this->collectiveVersionMapper->findVersionForFileId($node->getId(), $revision);

		$versionEntity->setMetadataValue($key, $value);
		$this->collectiveVersionMapper->update($versionEntity);
	}

	public function deleteVersion(IVersion $version): void {
		if (!$this->currentUserHasPermissions($version->getSourceFile(), Constants::PERMISSION_DELETE)) {
			throw new NotPermittedException('Failed to delete version');
		}

		$sourceFile = $version->getSourceFile();
		$mount = $sourceFile->getMountPoint();

		if (!($mount instanceof CollectiveMountPoint)) {
			return;
		}

		$versionsFolder = $this->getVersionsFolder($this->getFolderIdForFile($sourceFile))->get((string)$sourceFile->getId());
		/** @var Folder $versionsFolder */
		$versionsFolder->get((string)$version->getRevisionId())->delete();

		$versionEntity = $this->collectiveVersionMapper->findVersionForFileId(
			$version->getSourceFile()->getId(),
			$version->getTimestamp(),
		);
		$this->collectiveVersionMapper->delete($versionEntity);
	}

	public function createVersionEntity(File $file): void {
		$versionEntity = new CollectiveVersionEntity();
		$versionEntity->setFileId($file->getId());
		$versionEntity->setTimestamp($file->getMTime());
		$versionEntity->setSize($file->getSize());
		$versionEntity->setMimetype($this->mimeTypeLoader->getId($file->getMimetype()));
		$versionEntity->setDecodedMetadata([]);
		$this->collectiveVersionMapper->insert($versionEntity);
	}

	public function updateVersionEntity(File $sourceFile, int $revision, array $properties): void {
		$versionEntity = $this->collectiveVersionMapper->findVersionForFileId($sourceFile->getId(), $revision);

		if (isset($properties['timestamp'])) {
			$versionEntity->setTimestamp($properties['timestamp']);
		}

		if (isset($properties['size'])) {
			$versionEntity->setSize($properties['size']);
		}

		if (isset($properties['mimetype'])) {
			$versionEntity->setMimetype($properties['mimetype']);
		}

		$this->collectiveVersionMapper->update($versionEntity);
	}

	public function deleteVersionsEntity(File $file): void {
		$this->collectiveVersionMapper->deleteAllVersionsForFileId($file->getId());
	}

	private function currentUserHasPermissions(FileInfo $sourceFile, int $permissions): bool {
		$currentUserId = $this->userSession->getUser()?->getUID();

		if ($currentUserId === null) {
			throw new NotFoundException('No user logged in');
		}

		return ($sourceFile->getPermissions() & $permissions) === $permissions;
	}

	public function importVersionsForFile(IUser $user, Node $source, Node $target, array $versions): void {
		$mount = $target->getMountPoint();
		if (!($mount instanceof CollectiveMountPoint)) {
			return;
		}

		$versionsFolder = $this->getVersionFolderForFile($target);

		foreach ($versions as $version) {
			// 1. Move the file to the new location
			if ($version->getTimestamp() !== $source->getMTime()) {
				$backend = $version->getBackend();
				$versionFile = $backend->getVersionFile($user, $source, $version->getRevisionId());
				$versionsFolder->newFile($version->getRevisionId(), $versionFile->fopen('r'));
			}

			// 2. Create the entity in the database
			$versionEntity = new CollectiveVersionEntity();
			$versionEntity->setFileId($target->getId());
			$versionEntity->setTimestamp($version->getTimestamp());
			$versionEntity->setSize($version->getSize());
			$versionEntity->setMimetype($this->mimeTypeLoader->getId($version->getMimetype()));
			if ($version instanceof IMetadataVersion) {
				$versionEntity->setDecodedMetadata($version->getMetadata());
			}

			$this->collectiveVersionMapper->insert($versionEntity);
		}
	}

	public function clearVersionsForFile(IUser $user, Node $source, Node $target): void {
		$folderId = $this->getFolderIdForFile($source);
		$this->deleteAllVersionsForFile($folderId, $target->getId());
	}

	public function getRevision(Node $node): int {
		return $node->getMTime();
	}

}
