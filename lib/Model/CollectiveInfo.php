<?php

declare(strict_types=1);

namespace OCA\Collectives\Model;

use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCP\Constants;
use RuntimeException;

/**
 * Class CollectiveInfo
 * @method string getName()
 * @method void setName(string $name)
 * @method int getLevel()
 * @method void setLevel(int $level)
 * @method string getShareToken()
 * @method void setShareToken(string $shareToken)
 * @method bool getIsPageShare()
 * @method void setIsPageShare(bool $isPageShare)
 * @method bool getShareEditable()
 * @method void setShareEditable(bool $shareEditable)
 * @method int getUserPageOrder()
 * @method bool getUserShowRecentPages()
 */
class CollectiveInfo extends Collective {
	public function __construct(Collective $collective,
		protected string $name,
		protected int $level = Member::LEVEL_MEMBER,
		protected ?string $shareToken = null,
		protected bool $isPageShare = false,
		protected bool $shareEditable = false,
		protected ?int $userPageOrder = Collective::defaultPageOrder,
		protected ?bool $userShowRecentPages = Collective::defaultShowRecentPages) {
		$this->id = $collective->getId();
		$this->circleUniqueId = $collective->getCircleId();
		$this->emoji = $collective->getEmoji();
		$this->permissions = $collective->getPermissions();
		$this->trashTimestamp = $collective->getTrashTimestamp();
		$this->pageMode = $collective->getPageMode();
	}

	public function getUserPermissions(): int {
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

	public function setUserPageOrder(int $userPageOrder): void {
		if (!array_key_exists($userPageOrder, Collective::pageOrders)) {
			throw new RuntimeException('Invalid userPageOrder value: ' . $userPageOrder);
		}
		$this->userPageOrder = $userPageOrder;
	}

	public function setUserShowRecentPages(bool $userShowRecentPages): void {
		$this->userShowRecentPages = $userShowRecentPages;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
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
			'shareEditable' => $this->canEdit() && $this->shareEditable,
			'userPageOrder' => $this->userPageOrder,
			'userShowRecentPages' => $this->userShowRecentPages,
		];
	}
}
