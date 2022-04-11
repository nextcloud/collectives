<?php

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class CollectiveShare
 * @method int getId()
 * @method void setId(int $value)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $value)
 * @method string getToken()
 * @method void setToken(string $value)
 * @method string getOwner()
 * @method void setOwner(string $value)
 */
class CollectiveShare extends Entity implements JsonSerializable {
	/** @var int */
	protected $collectiveId;

	/** @var string */
	protected $token;

	/** @var string */
	protected $owner;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'collectiveId' => (int)$this->collectiveId,
			'token' => $this->token,
			'owner' => $this->owner,
		];
	}
}
