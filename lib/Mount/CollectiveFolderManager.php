<?php

namespace OCA\Collectives\Mount;

use OC\Files\Node\LazyFolder;
use OC\SystemConfig;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;

class CollectiveFolderManager {
	public const SKELETON_DIR = 'skeleton';
	public const LANDING_PAGE = 'README.md';

	/** @var SystemConfig */
	private $systemConfig;

	/** @var IRootFolder */
	private $rootFolder;

	/**
	 * MountProvider constructor.
	 *
	 * @param SystemConfig $systemConfig
	 * @param IRootFolder              $rootFolder
	 */
	public function __construct(
		IRootFolder $rootFolder,
		SystemConfig $systemConfig) {
		$this->systemConfig = $systemConfig;
		$this->rootFolder = $rootFolder;
	}

	public function getRootPath(): string {
		$instanceId = $this->systemConfig->getValue('instanceid', null);
		if (null === $instanceId) {
			throw new \RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId . '/collectives';
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
	 * @param Folder $folder
	 *
	 * @return Folder
	 * @throws NotPermittedException
	 */
	public function getSkeletonFolder(Folder $folder): Folder {
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
	 * @param int  $id
	 * @param bool $create
	 *
	 * @return Folder|null
	 * @throws NotPermittedException
	 */
	public function getFolder(int $id, bool $create = true): ?Folder {
		try {
			$folder = $this->getRootFolder()->get((string)$id);
			if (!$folder instanceof Folder) {
				return null;
			}
		} catch (NotFoundException $e) {
			if (!$create) {
				return null;
			}

			$folder = $this->getSkeletonFolder($this->getRootFolder())
				->copy($this->getRootFolder()->getPath() . '/' . (string)$id);
		}

		return $folder;
	}
}
