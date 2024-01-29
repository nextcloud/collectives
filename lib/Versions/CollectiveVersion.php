<?php

declare(strict_types=1);

namespace OCA\Collectives\Versions;

use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\Version;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IUser;

class CollectiveVersion extends Version {
	public function __construct(int $timestamp,
		int $revisionId,
		string $name,
		int $size,
		string $mimetype,
		string $path,
		FileInfo $sourceFileInfo,
		IVersionBackend $backend,
		IUser $user,
		private File $versionFile,
		private int $folderId) {
		parent::__construct($timestamp, $revisionId, $name, $size, $mimetype, $path, $sourceFileInfo, $backend, $user);
	}

	public function getVersionFile(): File {
		return $this->versionFile;
	}

	public function getFolderId(): int {
		return $this->folderId;
	}
}
