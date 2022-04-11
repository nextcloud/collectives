<?php

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class Collective
 * @method int getId()
 * @method void setId(int $value)
 * @method int getFileId()
 * @method void setFileId(int $value)
 * @method string getLastUserId()
 * @method void setLastUserId(string $value)
 */
class Page extends Entity implements JsonSerializable {
	/** @var int */
	protected $fileId;

	/** @var string */
	protected $lastUserId;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'fileId' => $this->fileId,
			'lastUserId' => $this->lastUserId
		];
	}
}
