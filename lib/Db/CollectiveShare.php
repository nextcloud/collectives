<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class CollectiveShare
 * @method int getId()
 * @method void setId(int $value)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $value)
 * @method int getPageId()
 * @method void setPageId(int $value)
 * @method string getToken()
 * @method void setToken(string $value)
 * @method string getOwner()
 * @method void setOwner(string $value)
 */
class CollectiveShare extends Entity implements JsonSerializable {
	protected ?int $collectiveId = null;
	protected int $pageId = 0;
	protected ?string $token = null;
	protected ?string $owner = null;
	/** transient attributes, not persisted in database  */
	protected bool $editable = false;
	protected string $password = '';

	public function getEditable(): bool {
		return $this->editable;
	}

	public function setEditable(bool $editable): void {
		$this->editable = $editable;
	}

	public function getPassword(): string {
		return $this->password;
	}

	public function setPassword(string $password): void {
		$this->password = $password;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'collectiveId' => (int)$this->collectiveId,
			'pageId' => $this->pageId,
			'token' => $this->token,
			'owner' => $this->owner,
			'editable' => $this->editable,
			'password' => $this->password,
		];
	}
}
