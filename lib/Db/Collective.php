<?php

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class Collective
 * @method int getId()
 * @method void setId(int $value)
 * @method string getEmoji()
 * @method void setEmoji(string $value)
 * @method string getCircleUniqueId()
 * @method void setCircleUniqueId(string $value)
 * @method string getConversationToken()
 * @method void setConversationToken(string $value)
 * @method int|null getTrashTimestamp()
 * @method void setTrashTimestamp(?int $value)
 */
class Collective extends Entity implements JsonSerializable {
	/** @var string */
	protected $circleUniqueId;

	/** @var string */
	protected $emoji;

	/** @var string */
	protected $conversationToken;

	/** @var int|null */
	protected $trashTimestamp;

	/**
	 * @return string|null
	 */
	public function getCircleId(): ?string {
		return $this->getCircleUniqueId();
	}

	/**
	 * @param string $circleId
	 */
	public function setCircleId(string $circleId): void {
		$this->setCircleUniqueId($circleId);
	}

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
			'circleId' => $this->circleUniqueId,
			'conversationToken' => $this->conversationToken,
			'trashTimestamp' => $this->trashTimestamp
		];
	}
}
