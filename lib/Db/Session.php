<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getToken()
 * @method void setToken(string $token)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $collectiveId)
 * @method int getLastContact()
 * @method void setLastContact(int $lastContact)
 */
class Session extends Entity implements JsonSerializable {
	protected ?string $userId = null;
	protected ?string $token = null;
	protected ?int $collectiveId = null;
	protected ?int $lastContact = null;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userId' => $this->userId,
			'token' => $this->token,
			'collectiveId' => $this->collectiveId,
			'lastContact' => $this->lastContact,
		];
	}
}
