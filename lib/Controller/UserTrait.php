<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCP\AppFramework\OCS\OCSForbiddenException;

/**
 * Trait providing standardized user ID handling for controllers.
 */
trait UserTrait {
	/**
	 * Get the current user ID or throw an OCS exception if not authenticated.
	 *
	 * @return string
	 * @throws OCSForbiddenException If user is not logged in
	 */
	protected function getUid(): string {
		if ($this->userId === null) {
			throw new OCSForbiddenException();
		}
		return $this->userId;
	}
}
