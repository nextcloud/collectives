<?php

namespace OCA\Collectives\Mount;

use OC\Files\Node\LazyFolder;
use OC\SystemConfig;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IDBConnection;

class CollectiveFolderManager {
	private const SKELETON_DIR = 'skeleton';
	private const LANDING_PAGE = 'Readme';
	private const LANDING_PAGE_SUFFIX = 'md';

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IDBConnection */
	private $connection;

	/** @var SystemConfig */
	private $systemConfig;

	/** @var string */
	private $rootPath;

	/**
	 * CollectiveFolderManager constructor.
	 *
	 * @param IRootFolder   $rootFolder
	 * @param IDBConnection $connection
	 * @param SystemConfig  $systemConfig
	 */
	public function __construct(
		IRootFolder $rootFolder,
		IDBConnection $connection,
		SystemConfig $systemConfig) {
		$this->rootFolder = $rootFolder;
		$this->connection = $connection;
		$this->systemConfig = $systemConfig;
	}

	public function getRootPath(): string {
		if (null !== $this->rootPath) {
			return $this->rootPath;
		}

		$instanceId = $this->systemConfig->getValue('instanceid', null);
		if (null === $instanceId) {
			throw new \RuntimeException('no instance id!');
		}

		$this->rootPath = 'appdata_' . $instanceId . '/collectives';
		return $this->rootPath;
	}

	/**
	 * @return Folder
	 */
	public function getRootFolder(): Folder {
		$rootFolder = $this->rootFolder;
		return (new LazyFolder(function () use ($rootFolder) {
			try {
				return $rootFolder->get($this->getRootPath());
			} catch (NotFoundException $e) {
				return $rootFolder->newFolder($this->getRootPath());
			}
		}));
	}

	/**
	 * @return int
	 * @throws NotFoundException
	 */
	private function getRootFolderStorageId(): int {
		$query = $this->connection->getQueryBuilder();

		$query->select('fileid')
			->from('filecache')
			->where($query->expr()->eq('storage', $query->createNamedParameter($this->getRootFolder()->getStorage()->getCache()->getNumericStorageId())))
			->andWhere($query->expr()->eq('path_hash', $query->createNamedParameter(md5($this->getRootPath()))));

		return (int)$query->execute()->fetchColumn();
	}

	/**
	 * @param Folder $folder
	 *
	 * @return Folder
	 * @throws NotPermittedException
	 */
	private function getSkeletonFolder(Folder $folder): Folder {
		try {
			$skeletonFolder = $folder->get(self::SKELETON_DIR);
			if (!$skeletonFolder instanceof Folder) {
				throw new NotFoundException('Not a folder: ' . $skeletonFolder->getPath());
			}
		} catch (NotFoundException $e) {
			$skeletonFolder = $folder->newFolder(self::SKELETON_DIR);
		}

		return $skeletonFolder;
	}

	/**
	 * @param string $lang
	 *
	 * @return string
	 */
	public function getLandingPagePath(string $path, string $lang): string {
		$landingPagePathEnglish = $path . '/' . self::LANDING_PAGE . '.en.' . self::LANDING_PAGE_SUFFIX;
		$landingPagePathLocalized = $path . '/' . self::LANDING_PAGE . '.' . $lang . '.' . self::LANDING_PAGE_SUFFIX;

		return file_exists($landingPagePathLocalized) ? $landingPagePathLocalized : $landingPagePathEnglish;
	}

	/**
	 * @param int $id
	 *
	 * @return array
	 *
	 * @throws NotFoundException
	 */
	public function getFolderFileCache(int $id): array {
		$query = $this->connection->getQueryBuilder();
		$query->select(
			'co.id AS folder_id', 'co.name AS mount_point',
			'fileid', 'storage', 'path', 'fc.name AS name', 'mimetype', 'mimepart', 'size', 'mtime', 'storage_mtime', 'etag', 'encrypted', 'parent', 'fc.permissions AS permissions')
			->from('collectives', 'co')
			->leftJoin('co', 'filecache', 'fc', $query->expr()->andX(
				// concat with empty string to work around missing cast to string
				$query->expr()->eq('fc.name', $query->func()->concat('co.id', $query->expr()->literal(''))),
				$query->expr()->eq('parent', $query->createNamedParameter($this->getRootFolderStorageId()))))
			->where($query->expr()->eq('co.id', $query->createNamedParameter($id)));
		return $query->execute()->fetch();
	}

	/**
	 * @return array
	 */
	public function getAllFolders(): array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from('collectives');
		$rows = $qb->execute()->fetchAll();

		$folderMap = [];
		foreach ($rows as $row) {
			$id = (int)$row['id'];
			$folderMap[$id] = [
				'id' => $id,
				'mount_point' => $row['name'],
			];
		}

		return $folderMap;
	}

	/**
	 * @param int  $id
	 *
	 * @returns Folder
	 * @throws NotFoundException
	 * @throws InvalidPathException
	 */
	public function getFolder(int $id): Folder {
		$folder = $this->getRootFolder()->get((string)$id);
		if (!$folder instanceof Folder) {
			throw new InvalidPathException('Not a folder: ' . $folder->getPath());
		}
		return $folder;
	}

	/**
	 * @param int  $id
	 * @param string $lang
	 *
	 * @throws InvalidPathException
	 * @throws NotPermittedException
	 */
	public function createFolder(int $id, string $lang) {
		$landingPageFileName = self::LANDING_PAGE . '.' . self::LANDING_PAGE_SUFFIX;
		try {
			$folder = $this->getFolder($id);
		} catch (NotFoundException $e) {
			$folder = $this->getSkeletonFolder($this->getRootFolder())
				->copy($this->getRootFolder()->getPath() . '/' . $id);
		}
		if (!$folder->nodeExists($landingPageFileName)) {
			$landingPageDir = __DIR__ . '/../../' . self::SKELETON_DIR;
			$landingPagePath = $this->getLandingPagePath($landingPageDir, $lang);
			if (false !== $content = file_get_contents($landingPagePath)) {
				$folder->newFile($landingPageFileName, $content);
			}
		}
	}

}
