<?php

namespace OCA\Unite\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class Collective
 * @method integer getId()
 * @method void setId(integer $value)
 * @method string getName()
 * @method void setName(string $value)
 * @method string getCircleUniqueId()
 * @method void setCircleUniqueId(string $value)
 * @method string getFolderId()
 * @method void setFolderId(integer $value)
 * @method string getOwnerId()
 * @method void setOwnerId(string $value)
 */
class Collective extends Entity implements JsonSerializable {
	protected $name;
	protected $circleUniqueId;
	protected $folderId;
	protected $ownerId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'circleUniqueId' => $this->circleUniqueId,
			'folderId' => $this->folderId,
			'ownerId' => $this->ownerId
		];
	}
}
