<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $collectiveId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getColor()
 * @method void setColor(string $color)
 */
class Tag extends Entity implements JsonSerializable {
	protected ?int $collectiveId = null;
	protected ?string $name = null;
	protected ?string $color = null;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'collectiveId' => $this->collectiveId,
			'name' => $this->name,
			'color' => $this->color,
		];
	}
}
