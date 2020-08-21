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
 */
class Collective extends Entity implements JsonSerializable {
	protected $name;
	protected $circleUniqueId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'circleUniqueId' => $this->circleUniqueId
		];
	}
}
