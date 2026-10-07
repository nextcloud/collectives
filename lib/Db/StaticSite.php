<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonException;
use JsonSerializable;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Db\Entity;
use UnexpectedValueException;

/**
 * Class StaticSite
 *
 * @method int getId()
 * @method void setId(int $value)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $value)
 * @method string getStaticSiteId()
 * @method void setStaticSiteId(string $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string getSlug()
 * @method void setSlug(string $value)
 * @method string getSelectedPages()
 * @method void setSelectedPages(string $value)
 * @method string|null getPublishedUrl()
 * @method void setPublishedUrl(?string $value)
 * @method string getStatus()
 * @method void setStatus(string $value)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $value)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $value)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $value)
 */
class StaticSite extends Entity implements JsonSerializable {
	public const STATUS_PENDING = 'pending';
	public const STATUS_PROVIDED = 'provided';
	public const STATUS_PUBLISHED = 'published';
	// Only set by the publish service, a failed archive build doesn't change the status
	public const STATUS_FAILED = 'failed';

	// TODO: Add STATUS_PROVIDED once the publish service gets informed about the provided tar.gz
	public const IN_PROGRESS_STATUSES = [
		self::STATUS_PENDING,
	];

	// Seconds after which a pending publication counts as aborted, e.g. after a PHP timeout
	public const PENDING_TIMEOUT = 15 * 60;

	protected ?int $collectiveId = null;
	protected ?string $staticSiteId = null;
	protected ?string $title = null;
	protected ?string $slug = null;
	protected ?string $selectedPages = null;
	protected ?string $publishedUrl = null;
	protected string $status = self::STATUS_PENDING;
	protected ?string $createdBy = null;
	protected ?int $createdAt = null;
	protected ?int $updatedAt = null;

	public function isInProgress(int $now): bool {
		return !($this->status === self::STATUS_PENDING && $this->updatedAt < $now - self::PENDING_TIMEOUT)
			&& in_array($this->status, self::IN_PROGRESS_STATUSES, true);
	}

	/**
	 * @throws UnexpectedValueException Stored page selection is corrupted
	 */
	public function getSelectedPageIds(): array {
		try {
			$pageIds = json_decode($this->selectedPages ?? '[]', true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new UnexpectedValueException('Invalid page selection stored for static site ' . $this->id, 0, $e);
		}
		if (!is_array($pageIds)) {
			throw new UnexpectedValueException('Invalid page selection stored for static site ' . $this->id);
		}
		return $pageIds;
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	public function setSelectedPageIds(array $pageIds): void {
		$this->setSelectedPages(self::encodePageIds($pageIds));
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	public static function encodePageIds(array $pageIds): string {
		try {
			return json_encode(array_values($pageIds), JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new UnprocessableEntityException('Invalid page IDs: ' . $e->getMessage(), 0, $e);
		}
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'collectiveId' => $this->collectiveId,
			'staticSiteId' => $this->staticSiteId,
			'title' => $this->title,
			'slug' => $this->slug,
			'selectedPageIds' => $this->getSelectedPageIds(),
			'publishedUrl' => $this->publishedUrl,
			'status' => $this->status,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
		];
	}
}
