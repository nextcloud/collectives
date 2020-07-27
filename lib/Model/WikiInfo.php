<?php

namespace OCA\Wiki\Model;

use JsonSerializable;

use OCA\Wiki\Db\Wiki;

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
	 * @param Wiki $wiki
	 */
	public function fromWiki(Wiki $wiki): void {
		$this->setId($wiki->getId());
		$this->setCircleUniqueId($wiki->getCircleUniqueId());
		$this->setFolderId($wiki->getFolderId());
		$this->setOwnerId($wiki->getOwnerId());
	}
}
