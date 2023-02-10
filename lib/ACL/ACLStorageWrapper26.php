<?php

declare(strict_types=1);

namespace OCA\Collectives\ACL;

use OCP\Constants;

class ACLStorageWrapper26 extends ACLStorageWrapper {
	/** @psalm-suppress ParseError */
	public function filesize($path): float|false|int {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filesize($path);
	}
}
