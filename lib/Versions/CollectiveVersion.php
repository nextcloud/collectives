<?php

namespace OCA\Collectives\Versions;

use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\Version;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IUser;

class CollectiveVersion extends Version {
	private File $versionFile;
	private int $folderId;

	/**
	 * CollectiveVersion constructor.
	 *
	 * @param int             $timestamp
	 * @param int             $revisionId
	 * @param string          $name
	 * @param int             $size
	 * @param string          $mimetype
	 * @param string          $path
	 * @param FileInfo        $sourceFileInfo
	 * @param IVersionBackend $backend
	 * @param IUser           $user
	 * @param File            $versionFile
	 * @param int             $folderId
	 */
	public function __construct(int $timestamp,
		int $revisionId,
		string $name,
		int $size,
		string $mimetype,
		string $path,
		FileInfo $sourceFileInfo,
		IVersionBackend $backend,
		IUser $user,
		File $versionFile,
		int $folderId) {
		parent::__construct($timestamp, $revisionId, $name, $size, $mimetype, $path, $sourceFileInfo, $backend, $user);
		$this->versionFile = $versionFile;
		$this->folderId = $folderId;
	}

	/**
	 * @return File
	 */
	public function getVersionFile(): File {
		return $this->versionFile;
	}

	/**
	 * @return int
	 */
	public function getFolderId(): int {
		return $this->folderId;
	}
}
