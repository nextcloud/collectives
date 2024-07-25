<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $value)
 * @method int getFileId()
 * @method void setFileId(int $value)
 * @method string getLastUserId()
 * @method void setLastUserId(string $value)
 * @method string getEmoji()
 * @method void setEmoji(?string $value)
 * @method string getSubpageOrder()
 * @method void setSubpageOrder(string $value)
 * @method bool getFullWidth()
 * @method void setFullWidth(bool $value)
 * @method int|null getTrashTimestamp()
 * @method void setTrashTimestamp(?int $trashTimestamp)
 */
class Page extends Entity implements JsonSerializable {
	protected ?int $fileId = null;
	protected ?string $lastUserId = null;
	protected ?string $emoji = null;
	protected ?string $subpageOrder = null;
	protected ?bool $fullWidth = null;
	protected ?int $trashTimestamp = null;

	public function __construct() {
		$this->addType('fullWidth', 'bool');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'fileId' => $this->fileId,
			'lastUserId' => $this->lastUserId,
			'emoji' => $this->emoji,
			'subpageOrder' => json_decode($this->getSubpageOrder() ?? '[]', true, 512, JSON_THROW_ON_ERROR),
			'fullWidth' => $this->fullWidth,
			'trashTimestamp' => $this->trashTimestamp,
		];
	}
}
