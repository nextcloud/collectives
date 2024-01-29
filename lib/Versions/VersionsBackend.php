<?php

declare(strict_types=1);

namespace OCA\Collectives\Versions;

use LogicException;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Mount\CollectiveMountPoint;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorage;
use OCP\IUser;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;

class VersionsBackend implements IVersionBackend {
	public function __construct(private CollectiveFolderManager $collectiveFolderManager,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger) {
	}

	private function getAppFolder(): Folder {
		return $this->collectiveFolderManager->getRootFolder();
	}

	public function useBackendForStorage(IStorage $storage): bool {
		return true;
	}

	/**
	 * @throws InvalidPathException
	 */
	public function getVersionsForFile(IUser $user, FileInfo $file): array {
		$mount = $file->getMountPoint();
		if ($mount instanceof CollectiveMountPoint) {
			try {
				$folderId = $mount->getFolderId();
				/** var Folder $versionsFolder */
				$versionsFolder = $this->getVersionsFolder($mount->getFolderId())->get((string)$file->getId());
				return array_map(function (Node $versionFile) use ($file, $user, $folderId) {
					if ($versionFile instanceof Folder) {
						$this->logger->error('Found an unexpected subfolder inside the collective version folder');
					}
					return new CollectiveVersion(
						(int)$versionFile->getName(),
						(int)$versionFile->getName(),
						$file->getName(),
						$versionFile->getSize(),
						$versionFile->getMimetype(),
						$versionFile->getPath(),
						$file,
						$this,
						$user,
						$versionFile,
						$folderId
					);
				}, $versionsFolder->getDirectoryListing());
			} catch (NotFoundException) {
				return [];
			}
		} else {
			return [];
		}
	}

	/**
	 * @throws NotPermittedException
	 */
	public function createVersion(IUser $user, FileInfo $file): void {
		$mount = $file->getMountPoint();
		if ($mount instanceof CollectiveMountPoint) {
			$folderId = $mount->getFolderId();
			$versionsFolder = $this->getVersionsFolder($folderId);

			try {
				/** @var Folder $versionFolder */
				$versionFolder = $versionsFolder->get((string)$file->getId());
			} catch (NotFoundException) {
				$versionFolder = $versionsFolder->newFolder((string)$file->getId());
			}

			$versionMount = $versionFolder->getMountPoint();
			$sourceMount = $file->getMountPoint();
			$sourceCache = $sourceMount->getStorage()->getCache();
			$revision = $this->timeFactory->getTime();

			$versionInternalPath = $versionFolder->getInternalPath() . '/' . $revision;
			$sourceInternalPath = $file->getInternalPath();

			$versionMount->getStorage()->copyFromStorage($sourceMount->getStorage(), $sourceInternalPath, $versionInternalPath);
			$versionMount->getStorage()->getCache()->copyFromCache($sourceCache, $sourceCache->get($sourceInternalPath), $versionInternalPath);
		}
	}

	/**
	 * @throws NotPermittedException
	 */
	public function rollback(IVersion $version): void {
		if ($version instanceof CollectiveVersion) {
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
		$mount = $sourceFile->getMountPoint();
		if (!($mount instanceof CollectiveMountPoint)) {
			throw new LogicException('Trying to getVersionFile from a file not in a mounted collective folder');
		}
		try {
			/** @var Folder $versionsFolder */
			$versionsFolder = $this->getVersionsFolder($mount->getFolderId())->get((string)$sourceFile->getId());
			return $versionsFolder->get((string)$revision);
		} catch (NotFoundException) {
			throw new LogicException('Trying to getVersionFile from a file that doesn\'t exist');
		}
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

		$fileIds = array_map(static fn (Node $node) => (int)$node->getName(), $contents);
		$files = array_map(static function (int $fileId) use ($mount): ?\OC\Files\FileInfo {
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
		} catch (NotFoundException) {
		}
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function deleteVersionsFolder(int $folderId): void {
		try {
			$versionsFolder = $this->getAppFolder()->get('versions/' . $folderId);
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
			return $this->getAppFolder()->get('versions/' . $folderId);
		} catch (NotFoundException) {
			/** @var Folder $versionsFolder */
			$versionsFolder = $this->getAppFolder()->nodeExists('versions') ? $this->getAppFolder()->get('versions') : $this->getAppFolder()->newFolder('versions');
			return $versionsFolder->newFolder((string)$folderId);
		}
	}
}
