<?php

namespace OCA\Unite\Model;

use OCA\Unite\Db\Collective;
use OCP\Files\Folder;

/**
 * Class CollectiveInfo
 * @method string getFolderName()
 * @method void setFolderName(string $value)
 * @method string getFolderPath()
 * @method void setFolderPath(string $value)
 */
class CollectiveInfo extends Collective {
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
	 * @param Collective  $collective
	 * @param Folder|null $folder
	 */
	public function fromCollective(
		Collective $collective,
		Folder $folder = null
	): void {
		$this->setId($collective->getId());
		$this->setCircleUniqueId($collective->getCircleUniqueId());
		$this->setFolderId($collective->getFolderId());
		$this->setOwnerId($collective->getOwnerId());
		$this->setName($collective->getName());
		if (null !== $folder) {
			$this->setFolderName($folder->getName());
			$this->setFolderPath($folder->getPath());
		}
	}
}
