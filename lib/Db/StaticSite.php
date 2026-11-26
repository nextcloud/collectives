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
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
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
 * @method string getTheme()
 * @method void setTheme(string $value)
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
 * @method string|null getLastError()
 * @method void setLastError(?string $value)
 * @method int|null getPublishedAt()
 * @method void setPublishedAt(?int $value)
 * @method string|null getBuildId()
 * @method void setBuildId(?string $value)
 * @method string|null getPublishedBuildId()
 * @method void setPublishedBuildId(?string $value)
 * @method string|null getPublishedTitle()
 * @method void setPublishedTitle(?string $value)
 * @method string|null getPublishedSelectedPages()
 * @method void setPublishedSelectedPages(?string $value)
 */
class StaticSite extends Entity implements JsonSerializable {
	public const STATUS_PENDING = 'pending';
	public const STATUS_PROVIDED = 'provided';
	public const STATUS_PUBLISHED = 'published';
	// The publish service didn't build the static site, e.g. it wasn't reachable or rejected it.
	// A failed archive build keeps the previous status instead.
	public const STATUS_FAILED = 'failed';
	// The publish service was requested to delete the published static site
	public const STATUS_DELETING = 'deleting';

	// Rendering of the website by the publish service: a page tree sidebar or a top navigation bar.
	// Keep in sync with the theme literal in ResponseDefinitions.php and src/util/staticSiteTheme.js
	public const THEME_WIKI = 'wiki';
	public const THEME_TOPNAV = 'topnav';
	public const ALLOWED_THEMES = [self::THEME_WIKI, self::THEME_TOPNAV];

	// Reasons for status failed, translated by the frontend
	public const ERROR_GENERATOR_UNAVAILABLE = 'generator_unavailable';
	public const ERROR_GENERATOR_REJECTED = 'generator_rejected';
	public const ERROR_GENERATOR_BUSY = 'generator_busy';
	public const ERROR_GENERATOR_FAILED = 'generator_failed';
	public const ERROR_GENERATOR_INVALID_URL = 'generator_invalid_url';
	public const ERROR_GENERATOR_DELETE_FAILED = 'generator_delete_failed';

	public const IN_PROGRESS_STATUSES = [
		self::STATUS_PENDING,
		self::STATUS_PROVIDED,
		self::STATUS_DELETING,
	];

	// Seconds after which an in-progress publication or deletion counts as aborted, e.g. after a PHP
	// timeout or an unreachable/crashed publish service that never sends its status callback. Must be longer
	// than the pending timeout of the publish service (600 s), which rejects new requests until then.
	public const PENDING_TIMEOUT = 15 * 60;

	protected ?int $collectiveId = null;
	protected ?string $staticSiteId = null;
	protected ?string $title = null;
	protected ?string $slug = null;
	protected string $theme = self::THEME_WIKI;
	protected ?string $selectedPages = null;
	protected ?string $publishedUrl = null;
	protected string $status = self::STATUS_PENDING;
	protected ?string $createdBy = null;
	protected ?int $createdAt = null;
	protected ?int $updatedAt = null;
	protected ?string $lastError = null;
	protected ?int $publishedAt = null;
	// Latest request to the static site generator, a build or a deletion. Its status callback is
	// assigned by it, and a build's archive is downloaded by it.
	protected ?string $buildId = null;
	// Build that is online, with its title and page selection
	protected ?string $publishedBuildId = null;
	protected ?string $publishedTitle = null;
	protected ?string $publishedSelectedPages = null;

	/**
	 * Whether the static site got published at least once, so the static site generator knows it.
	 */
	public function isPublished(): bool {
		return $this->publishedUrl !== null;
	}

	public function isInProgress(int $now): bool {
		return in_array($this->status, self::IN_PROGRESS_STATUSES, true)
			&& $this->updatedAt >= $now - self::PENDING_TIMEOUT;
	}

	/**
	 * @return ?int Seconds until a request in progress counts as aborted, null if none is in progress
	 */
	public function getSecondsUntilTimeout(int $now): ?int {
		return $this->isInProgress($now)
			? $this->updatedAt + self::PENDING_TIMEOUT - $now
			: null;
	}

	/**
	 * @throws UnexpectedValueException Stored page selection is corrupted
	 */
	public function getSelectedPageIds(): array {
		return $this->decodePageIds($this->selectedPages ?? '[]');
	}

	/**
	 * @return ?array Page selection of the build that is online, null if none is
	 *
	 * @throws UnexpectedValueException Stored page selection is corrupted
	 */
	public function getPublishedSelectedPageIds(): ?array {
		return $this->publishedSelectedPages === null ? null : $this->decodePageIds($this->publishedSelectedPages);
	}

	/**
	 * Time the build that is online started, null if none is or it isn't known
	 *
	 * Pages edited after this time aren't part of the publication. Build IDs are UUIDv7,
	 * which contain the time they were created.
	 */
	public function getPublishedBuildStartedAt(): ?int {
		if ($this->publishedBuildId === null || !Uuid::isValid($this->publishedBuildId)) {
			return null;
		}
		$uuid = Uuid::fromString($this->publishedBuildId);
		return $uuid instanceof UuidV7 ? $uuid->getDateTime()->getTimestamp() : null;
	}

	/**
	 * @throws UnexpectedValueException
	 */
	private function decodePageIds(string $encoded): array {
		try {
			$pageIds = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
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

	/**
	 * Omits `staticSiteId` and the build IDs, they authorize the static site generator's callback and archive download.
	 * `isInProgress` is evaluated with the server time, so clients don't need to know the timeout.
	 */
	public function jsonSerialize(): array {
		$now = time();
		return [
			'id' => $this->id,
			'collectiveId' => $this->collectiveId,
			'title' => $this->title,
			'slug' => $this->slug,
			'theme' => $this->theme,
			'selectedPageIds' => $this->getSelectedPageIds(),
			'publishedUrl' => $this->publishedUrl,
			'status' => $this->status,
			'createdBy' => $this->createdBy,
			'createdAt' => $this->createdAt,
			'updatedAt' => $this->updatedAt,
			'lastError' => $this->lastError,
			'publishedAt' => $this->publishedAt,
			'publishedTitle' => $this->publishedTitle,
			'publishedSelectedPageIds' => $this->getPublishedSelectedPageIds(),
			'publishedBuildStartedAt' => $this->getPublishedBuildStartedAt(),
			'isInProgress' => $this->isInProgress($now),
			'secondsUntilTimeout' => $this->getSecondsUntilTimeout($now),
		];
	}
}
