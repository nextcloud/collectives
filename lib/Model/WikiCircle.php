<?php

namespace OCA\Wiki\Model;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class WikiCircle
 * @method integer getUniqueId()
 * @method void setUniqueId(string $value)
 * @method integer getName()
 * @method void setName(string $value)
 * @method integer getFileId()
 * @method void setFileId(integer $value)
 */
class WikiCircle extends Entity implements JsonSerializable {
	protected $uniqueId;
	protected $name;
	protected $fileId;

	public function jsonSerialize() {
		return [
			'uniqueId' => $this->uniqueId,
			'name' => $this->name,
			'file_id' => $this->fileId
		];
	}
}
