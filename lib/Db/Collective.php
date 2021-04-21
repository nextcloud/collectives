<?php

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class Collective
 * @method integer getId()
 * @method void setId(integer $value)
 * @method integer getEmoji()
 * @method void setEmoji(string $value)
 * @method string getCircleUniqueId()
 * @method void setCircleUniqueId(string $value)
 * @method string getTrashTimestamp()
 * @method void setTrashTimestamp(int $value = null)
 */
class Collective extends Entity implements JsonSerializable {
	/** @var string */
	protected $circleUniqueId;

	/** @var string */
	protected $emoji;

	/** @var int */
	protected $trashTimestamp;

	/**
	 * @return bool
	 */
	public function isTrashed(): bool {
		return (bool)$this->getTrashTimestamp();
	}

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'emoji' => $this->emoji,
			'circleUniqueId' => $this->circleUniqueId,
			'trashTimestamp' => $this->trashTimestamp
		];
	}
}
