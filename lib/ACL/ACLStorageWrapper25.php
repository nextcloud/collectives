<?php

declare(strict_types=1);

namespace OCA\Collectives\ACL;

use OCP\Constants;

class ACLStorageWrapper25 extends ACLStorageWrapper {
	public function filesize($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filesize($path);
	}
}
