<?php

namespace OCA\Collectives\Model;

use OCA\Collectives\Db\Collective;

/**
 * Class CollectiveInfo
 * @method integer getAdmin()
 * @method void setAdmin(bool $value)
 */
class CollectiveInfo extends Collective {
	protected $admin;

	public function __construct(Collective $collective, bool $admin = false) {
		$this->id = $collective->getId();
		$this->name = $collective->getName();
		$this->circleUniqueId = $collective->getCircleUniqueId();
		$this->admin = $admin;
	}

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'circleUniqueId' => $this->circleUniqueId,
			'admin' => $this->admin,
		];
	}
}
