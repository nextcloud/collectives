<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * Class Publication
 *
 * @method int getId()
 * @method void setId(int $value)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $value)
 * @method string getStaticSiteId()
 * @method void setStaticSiteId(string $value)
 * @method string getSelectedPages()
 * @method void setSelectedPages(string $value)
 * @method string|null getPublishedUrl()
 * @method void setPublishedUrl(?string $value)
 * @method string getStatus()
 * @method void setStatus(string $value)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $value)
 * @method int getCreated()
 * @method void setCreated(int $value)
 * @method int getLastUpdated()
 * @method void setLastUpdated(int $value)
 */
class Publication extends Entity implements JsonSerializable {
	public const STATUS_PENDING = 'pending';
	public const STATUS_PROVIDED = 'provided';
	public const STATUS_FETCHED = 'fetched';
	public const STATUS_PUBLISHED = 'published';
	public const STATUS_FAILED = 'failed';

	protected ?int $collectiveId = null;
	protected ?string $staticSiteId = null;
	protected ?string $selectedPages = null;
	protected ?string $publishedUrl = null;
	protected string $status = self::STATUS_PENDING;
	protected ?string $createdBy = null;
	protected ?int $created = null;
	protected ?int $lastUpdated = null;

	/**
	 * @return int[]
	 */
	public function getSelectedPageIds(): array {
		return json_decode($this->selectedPages ?? '[]', true, 512, JSON_THROW_ON_ERROR);
	}

	/**
	 * @param int[] $pageIds
	 */
	public function setSelectedPageIds(array $pageIds): void {
		$this->setSelectedPages(json_encode(array_values($pageIds), JSON_THROW_ON_ERROR));
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'collectiveId' => $this->collectiveId,
			'staticSiteId' => $this->staticSiteId,
			'selectedPageIds' => $this->getSelectedPageIds(),
			'publishedUrl' => $this->publishedUrl,
			'status' => $this->status,
			'createdBy' => $this->createdBy,
			'created' => $this->created,
			'lastUpdated' => $this->lastUpdated,
		];
	}
}
