<?php

namespace OCA\Wiki\Model;

use OCA\Wiki\Db\Wiki;
use OCP\Files\Folder;

/**
 * Class WikiInfo
 * @method string getFolderName()
 * @method void setFolderName(string $value)
 * @method string getFolderPath()
 * @method void setFolderPath(string $value)
 */
class WikiInfo extends Wiki {
	protected $folderName;
	protected $folderPath;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'circleUniqueId' => $this->circleUniqueId,
			'folderId' => $this->folderId,
			'ownerId' => $this->ownerId,
			'folderName' => $this->folderName,
			'folderPath' => $this->folderPath
		];
	}

	/**
	 * @param Wiki        $wiki
	 * @param Folder|null $folder
	 */
	public function fromWiki(
		Wiki $wiki,
		Folder $folder = null
	): void {
		$this->setId($wiki->getId());
		$this->setCircleUniqueId($wiki->getCircleUniqueId());
		$this->setFolderId($wiki->getFolderId());
		$this->setOwnerId($wiki->getOwnerId());
		if (null !== $folder) {
			$this->setFolderName($folder->getName());
			$this->setFolderPath($folder->getPath());
		}
	}
}
