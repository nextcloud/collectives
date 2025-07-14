<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Model;

use JsonSerializable;

use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;

class PageInfo implements JsonSerializable {
	public const INDEX_PAGE_TITLE = 'Readme';
	public const SUFFIX = '.md';

	private int $id;
	private ?string $slug = null;
	private ?string $lastUserId = null;
	private ?string $lastUserDisplayName = null;
	private ?string $emoji = null;
	private ?string $subpageOrder = null;
	private bool $fullWidth = false;
	private ?string $tags = null;
	private ?int $trashTimestamp = null;
	private string $title;
	private int $timestamp;
	private int $size;
	private string $fileName;
	private string $filePath;
	private ?string $collectivePath = null;
	private int $parentId;
	private ?string $shareToken = null;

	public function getId(): int {
		return $this->id;
	}

	public function setId(int $id): void {
		$this->id = $id;
	}

	public function getSlug(): ?string {
		return $this->slug;
	}

	public function setSlug(?string $slug): void {
		$this->slug = $slug;
	}

	public function getLastUserId(): ?string {
		return $this->lastUserId;
	}

	public function setLastUserId(string $lastUserId): void {
		$this->lastUserId = $lastUserId;
	}

	public function getLastUserDisplayName(): ?string {
		return $this->lastUserDisplayName;
	}

	public function setLastUserDisplayName(string $lastUserDisplayName): void {
		$this->lastUserDisplayName = $lastUserDisplayName;
	}

	public function getEmoji(): ?string {
		return $this->emoji;
	}

	public function setEmoji(?string $emoji): void {
		$this->emoji = $emoji;
	}

	public function getSubpageOrder(): ?string {
		return $this->subpageOrder;
	}

	public function setSubpageOrder(string $subpageOrder): void {
		$this->subpageOrder = $subpageOrder;
	}

	public function isFullWidth(): bool {
		return $this->fullWidth;
	}

	public function setFullWidth(bool $fullWidth): void {
		$this->fullWidth = $fullWidth;
	}

	public function getTags(): ?string {
		return $this->tags;
	}

	public function setTags(string $tags): void {
		$this->tags = $tags;
	}

	public function getTrashTimestamp(): ?int {
		return $this->trashTimestamp;
	}

	public function setTrashTimestamp(int $trashTimestamp): void {
		$this->trashTimestamp = $trashTimestamp;
	}

	public function getTitle(): string {
		return $this->title;
	}

	public function setTitle(string $title): void {
		$this->title = $title;
	}

	public function getTimestamp(): int {
		return $this->timestamp;
	}

	public function setTimestamp(int $timestamp): void {
		$this->timestamp = $timestamp;
	}

	public function getSize(): int {
		return $this->size;
	}

	public function setSize(int $size): void {
		$this->size = $size;
	}

	public function getFileName(): string {
		return $this->fileName;
	}

	public function setFileName(string $fileName): void {
		$this->fileName = $fileName;
	}

	public function getFilePath(): string {
		return $this->filePath;
	}

	public function getFilePathString(): string {
		return str_replace(DIRECTORY_SEPARATOR, ' - ', $this->getFilePath());
	}

	public function setFilePath(string $filePath): void {
		$this->filePath = $filePath;
	}

	public function getCollectivePath(): ?string {
		return $this->collectivePath;
	}

	public function setCollectivePath(string $collectivePath): void {
		$this->collectivePath = $collectivePath;
	}

	public function getParentId(): int {
		return $this->parentId;
	}

	public function setParentId(int $parentId): void {
		$this->parentId = $parentId;
	}

	public function getShareToken(): ?string {
		return $this->shareToken;
	}

	public function setShareToken(string $shareToken): void {
		$this->shareToken = $shareToken;
	}

	public function getUrlPath(): string {
		return $this->slug ? $this->slug . '-' . $this->id : $this->title;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'slug' => $this->slug,
			'lastUserId' => $this->lastUserId,
			'lastUserDisplayName' => $this->lastUserDisplayName,
			'emoji' => $this->emoji,
			'subpageOrder' => json_decode($this->subpageOrder ?? '[]', true, 512, JSON_THROW_ON_ERROR),
			'isFullWidth' => $this->fullWidth,
			'tags' => json_decode($this->tags ?? '[]', true, 512, JSON_THROW_ON_ERROR),
			'trashTimestamp' => $this->trashTimestamp,
			'title' => $this->title,
			'timestamp' => $this->timestamp,
			'size' => $this->size,
			'fileName' => $this->fileName,
			'filePath' => $this->filePath,
			'filePathString' => $this->getFilePathString(),
			'collectivePath' => $this->collectivePath,
			'parentId' => $this->parentId,
			'shareToken' => $this->shareToken,
		];
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function fromFile(File $file, int $parentId, ?string $lastUserId = null, ?string $lastUserDisplayName = null, ?string $emoji = null, ?string $subpageOrder = null, ?bool $fullWidth = false, ?string $slug = null, ?string $tags = null): void {
		$this->setId($file->getId());
		// Set folder name as title for all index pages except the collective landing page
		$dirName = dirname($file->getInternalPath());
		$dirName = $dirName === '.' ? '' : $dirName;
		if (strcmp($file->getName(), self::INDEX_PAGE_TITLE . self::SUFFIX) === 0) {
			if ($parentId === 0) {
				// Landing page
				$this->setTitle(\OC::$server->getL10N('collectives')->t('Landing page'));
			} else {
				// Index page
				$this->setTitle(basename($dirName));
			}
		} else {
			$this->setTitle(basename($file->getName(), self::SUFFIX));
		}
		$this->setFilePath($dirName);
		$this->setTimestamp($file->getMTime());
		$this->setSize((int)$file->getSize());
		$this->setFileName($file->getName());
		$mountPoint = explode('/', $file->getMountPoint()->getMountPoint(), 4);
		if (count($mountPoint) >= 4) {
			$this->setCollectivePath(rtrim($mountPoint[3], '/'));
		}
		if ($lastUserId !== null) {
			$this->setLastUserId($lastUserId);
		}
		if ($lastUserDisplayName !== null) {
			$this->setLastUserDisplayName($lastUserDisplayName);
		}
		if ($emoji !== null) {
			$this->setEmoji($emoji);
		}
		if ($fullWidth !== null) {
			$this->setFullWidth($fullWidth);
		}
		if ($subpageOrder !== null) {
			$this->setSubpageOrder($subpageOrder);
		}
		if ($slug !== null) {
			$this->setSlug($slug);
		}
		if ($tags !== null) {
			$this->setTags($tags);
		}
		$this->setParentId($parentId);
	}
}
