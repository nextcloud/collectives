<?php

declare(strict_types=1);

namespace OCA\Collectives\ACL;

use OC\Files\Cache\Cache;
use OC\Files\Cache\Scanner;
use OC\Files\Cache\Wrapper\CacheWrapper;
use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Constants;

class ACLStorageWrapper25 extends ACLStorageWrapper {
	public function filesize($path) {
		if (!$this->checkPermissions(Constants::PERMISSION_READ)) {
			return false;
		}
		return parent::filesize($path);
	}
}
