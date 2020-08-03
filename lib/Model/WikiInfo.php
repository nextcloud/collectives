<?php

namespace OCA\Wiki\Model;

use OCA\Wiki\Db\Wiki;
use OCP\Files\Folder;

/**
 * Class WikiInfo
 * @method string getName()
 * @method void setName(string $value)
 * @method string getFolderName()
 * @method void setFolderName(string $value)
 * @method string getFolderPath()
 * @method void setFolderPath(string $value)
 */
class WikiInfo extends Wiki {
	protected $name;
	protected $folderName;
	protected $folderPath;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'circleUniqueId' => $this->circleUniqueId,
			'folderId' => $this->folderId,
			'ownerId' => $this->ownerId,
			'name' => $this->name,
			'folderName' => $this->folderName,
			'folderPath' => $this->folderPath
		];
	}

	/**
	 * @param Wiki        $wiki
	 * @param string      $name
	 * @param Folder|null $folder
	 */
	public function fromWiki(
		Wiki $wiki,
		string $name,
		Folder $folder = null
	): void {
		$this->setId($wiki->getId());
		$this->setCircleUniqueId($wiki->getCircleUniqueId());
		$this->setFolderId($wiki->getFolderId());
		$this->setOwnerId($wiki->getOwnerId());
		$this->setName($name);
		if (null !== $folder) {
			$this->setFolderName($folder->getName());
			$this->setFolderPath($folder->getPath());
		}
	}
}
