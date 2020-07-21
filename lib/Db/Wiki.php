<?php

namespace OCA\Wiki\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class Wiki
 * @method integer getId()
 * @method void setId(integer $value)
 * @method string getCircleUniqueId()
 * @method void setCircleUniqueId(string $value)
 * @method string getFileId()
 * @method void setFileId(integer $value)
 * @method string getOwnerId()
 * @method void setOwnerId(string $value)
 */
class Wiki extends Entity implements JsonSerializable {
	protected $circleUniqueId;
	protected $fileId;
	protected $ownerId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'circleUniqueId' => $this->circleUniqueId,
			'fileId' => $this->fileId,
			'ownerId' => $this->ownerId
		];
	}
}
