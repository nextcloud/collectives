<?php

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class Collective
 * @method integer getId()
 * @method void setId(integer $value)
 * @method integer getFileId()
 * @method void setFileId(integer $value)
 * @method string getLastUserId()
 * @method void setLastUserId(string $value)
 */
class Page extends Entity implements JsonSerializable {
	protected $fileId;
	protected $lastUserId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'fileId' => $this->fileId,
			'lastUserId' => $this->lastUserId
		];
	}
}
