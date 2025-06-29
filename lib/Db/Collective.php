<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonSerializable;
use OCA\Circles\Model\Member;

use OCP\AppFramework\Db\Entity;
use OCP\Constants;
use RuntimeException;

/**
 * @method int getId()
 * @method void setId(int $value)
 * @method string getSlug()
 * @method void setSlug(?string $value)
 * @method string getCircleUniqueId()
 * @method void setCircleUniqueId(string $circleUniqueId)
 * @method int getPermissions()
 * @method void setPermissions(int $permissions)
 * @method string getEmoji()
 * @method void setEmoji(?string $emoji)
 * @method int|null getTrashTimestamp()
 * @method void setTrashTimestamp(?int $trashTimestamp)
 * @method int getPageMode()
 */
class Collective extends Entity implements JsonSerializable {
	/** @var int */
	public const defaultPermissions
		= Constants::PERMISSION_ALL * 100 // Moderator
		+ Constants::PERMISSION_ALL;        // Member

	public const editPermissions
		= Constants::PERMISSION_UPDATE
		+ Constants::PERMISSION_CREATE
		+ Constants::PERMISSION_DELETE;

	public const pageOrders = [
		0 => 'byOrder',
		1 => 'byTimeAsc',
		2 => 'byTitleAsc',
		3 => 'byTimeDesc',
		4 => 'byTitleDesc',
	];
	public const defaultPageOrder = 0;

	public const pageModes = [
		0 => 'view',
		1 => 'edit',
	];
	/** @var int */
	public const defaultPageMode = 0;
	/** @var bool */
	public const defaultShowMembers = true;
	/** @var bool */
	public const defaultShowRecentPages = true;

	protected ?string $circleUniqueId = null;
	protected int $permissions = self::defaultPermissions;
	protected ?string $slug = null;
	protected ?string $emoji = null;
	protected ?int $trashTimestamp = null;
	protected int $pageMode = self::defaultPageMode;
	/** transient attributes, not persisted in database  */
	protected string $name = '';
	protected int $level = Member::LEVEL_MEMBER;
	protected ?string $shareToken = null;
	protected bool $isPageShare = false;
	protected int $sharePageId = 0;
	protected bool $shareEditable = false;
	protected int $userPageOrder = Collective::defaultPageOrder;
	protected bool $userShowMembers = Collective::defaultShowMembers;
	protected bool $userShowRecentPages = Collective::defaultShowRecentPages;
	protected array $userFavoritePages = [];
	protected bool $canLeave = false;

	public function getCircleId(): string {
		return $this->getCircleUniqueId();
	}

	public function setCircleId(string $circleId): void {
		$this->setCircleUniqueId($circleId);
	}

	public function getModeratorPermissions(): int {
		// moderator permissions are stored in thousands and hundreds
		return intdiv($this->permissions, 100) % 100;
	}

	public function setModeratorPermissions(int $permissions): void {
		// moderator permissions are stored in thousands and hundreds
		$this->setPermissions(
			$permissions * 100          // Moderator
			+ $this->getMemberPermissions() // Member
		);
	}

	public function getMemberPermissions(): int {
		// member permissions are stored in tens and ones
		return $this->permissions % 100;
	}

	public function setMemberPermissions(int $permissions): void {
		// member permissions are stored in tens and ones
		$this->setPermissions(
			$this->getModeratorPermissions() * 100 // Moderator
			+ $permissions                             // Member
		);
	}

	public function updatePermissionLevel(int $level, int $permission): void {
		if ($level >= Member::LEVEL_ADMIN) {
			$this->setModeratorPermissions($this->getModeratorPermissions() & ~$permission);
			$this->setMemberPermissions($this->getMemberPermissions() & ~$permission);
		} elseif ($level >= Member::LEVEL_MODERATOR) {
			$this->setModeratorPermissions($this->getModeratorPermissions() | $permission);
			$this->setMemberPermissions($this->getMemberPermissions() & ~$permission);
		} else {
			$this->setModeratorPermissions($this->getModeratorPermissions() | $permission);
			$this->setMemberPermissions($this->getMemberPermissions() | $permission);
		}
	}

	public function setPageMode(int $mode): void {
		if (!array_key_exists($mode, Collective::pageModes)) {
			throw new RuntimeException('Invalid pageMode value: ' . $mode);
		}
		$this->pageMode = $mode;
		$this->markFieldUpdated('pageMode');
	}

	public function isTrashed(): bool {
		return (bool)$this->getTrashTimestamp();
	}

	public function getName(): string {
		return $this->name;
	}

	public function setName(string $name): void {
		$this->name = $name;
	}

	public function getLevel(): int {
		return $this->level;
	}

	public function setLevel(int $level): void {
		$this->level = $level;
	}

	public function getShareToken(): ?string {
		return $this->shareToken;
	}

	public function setShareToken(string $shareToken): void {
		$this->shareToken = $shareToken;
	}

	public function getIsPageShare(): bool {
		return $this->isPageShare;
	}

	public function setIsPageShare(bool $isPageShare): void {
		$this->isPageShare = $isPageShare;
	}

	public function getSharePageId(): int {
		return $this->sharePageId;
	}

	public function setSharePageId(int $sharePageId): void {
		$this->sharePageId = $sharePageId;
	}

	public function getShareEditable(): bool {
		return $this->shareEditable;
	}

	public function setShareEditable(bool $shareEditable): void {
		$this->shareEditable = $shareEditable;
	}

	public function getUserPageOrder(): int {
		return $this->userPageOrder;
	}

	public function getUserShowMembers(): bool {
		return $this->userShowMembers;
	}

	public function getUserShowRecentPages(): bool {
		return $this->userShowRecentPages;
	}

	public function getUserFavoritePages(): array {
		return $this->userFavoritePages;
	}

	public function getCanLeave(): bool {
		return $this->canLeave;
	}

	public function setCanLeave(bool $canLeave): void {
		$this->canLeave = $canLeave;
	}

	/**
	 * @throws RuntimeException
	 */
	public function setUserPageOrder(int $userPageOrder): void {
		if (!array_key_exists($userPageOrder, self::pageOrders)) {
			throw new RuntimeException('Invalid userPageOrder value: ' . $userPageOrder);
		}
		$this->userPageOrder = $userPageOrder;
	}

	public function setUserShowMembers(bool $userShowMembers): void {
		$this->userShowMembers = $userShowMembers;
	}

	public function setUserShowRecentPages(bool $userShowRecentPages): void {
		$this->userShowRecentPages = $userShowRecentPages;
	}

	public function setUserFavoritePages(array $userFavoritePages): void {
		$this->userFavoritePages = $userFavoritePages;
	}

	public function getUserPermissions(bool $isShare = false): int {
		// Public shares always get permissions of a simple member plus sharing permission of owner
		if ($isShare) {
			$sharePermissions = $this->canShare() ? Constants::PERMISSION_SHARE : 0;
			return $this->getMemberPermissions() | $sharePermissions;
		}

		if ($this->level === Member::LEVEL_OWNER || $this->level === Member::LEVEL_ADMIN) {
			return Constants::PERMISSION_ALL;
		}

		if ($this->level === Member::LEVEL_MODERATOR) {
			return $this->getModeratorPermissions();
		}

		return $this->getMemberPermissions();
	}

	private function getPermissionLevel(int $permission): int {
		if (($this->getMemberPermissions() | $permission) === $this->getMemberPermissions()) {
			return Member::LEVEL_MEMBER;
		}

		if (($this->getModeratorPermissions() | $permission) === $this->getModeratorPermissions()) {
			return Member::LEVEL_MODERATOR;
		}

		return Member::LEVEL_ADMIN;
	}

	public function getEditPermissionLevel(): int {
		return $this->getPermissionLevel(Collective::editPermissions);
	}

	public function getSharePermissionLevel(): int {
		return $this->getPermissionLevel(Constants::PERMISSION_SHARE);
	}

	public function canEdit(): bool {
		return $this->level >= $this->getEditPermissionLevel();
	}

	public function canShare(): bool {
		return $this->level >= $this->getSharePermissionLevel();
	}

	public function getUrlPath(): string {
		return $this->slug ? $this->slug . '-' . $this->id : $this->name;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'slug' => $this->slug,
			'circleId' => $this->circleUniqueId,
			'emoji' => $this->emoji,
			'trashTimestamp' => $this->trashTimestamp,
			'pageMode' => $this->pageMode,
			'name' => $this->name,
			'level' => $this->level,
			'editPermissionLevel' => $this->getEditPermissionLevel(),
			'sharePermissionLevel' => $this->getSharePermissionLevel(),
			'canEdit' => $this->canEdit(),
			'canShare' => $this->canShare(),
			'shareToken' => $this->shareToken,
			'isPageShare' => $this->isPageShare,
			'sharePageId' => $this->sharePageId,
			'shareEditable' => $this->canEdit() && $this->shareEditable,
			'userPageOrder' => $this->userPageOrder,
			'userShowMembers' => $this->userShowMembers,
			'userShowRecentPages' => $this->userShowRecentPages,
			'userFavoritePages' => $this->userFavoritePages,
			'canLeave' => $this->getCanLeave(),
		];
	}
}
