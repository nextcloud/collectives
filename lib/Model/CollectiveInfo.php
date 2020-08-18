<?php

namespace OCA\Unite\Model;

use OCA\Unite\Db\Collective;
use OCP\Files\Folder;

/**
 * Class CollectiveInfo
 * @method string getName()
 * @method void setName(string $value)
 * @method string getFolderName()
 * @method void setFolderName(string $value)
 * @method string getFolderPath()
 * @method void setFolderPath(string $value)
 */
class CollectiveInfo extends Collective {
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
	 * @param Collective  $collective
	 * @param string      $name
	 * @param Folder|null $folder
	 */
	public function fromCollective(
		Collective $collective,
		string $name,
		Folder $folder = null
	): void {
		$this->setId($collective->getId());
		$this->setCircleUniqueId($collective->getCircleUniqueId());
		$this->setFolderId($collective->getFolderId());
		$this->setOwnerId($collective->getOwnerId());
		$this->setName($name);
		if (null !== $folder) {
			$this->setFolderName($folder->getName());
			$this->setFolderPath($folder->getPath());
		}
	}
}
