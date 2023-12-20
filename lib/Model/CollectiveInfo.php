<?php

declare(strict_types=1);

namespace OCA\Collectives\Model;

use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCP\Constants;

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
	protected string $name;
	protected int $level;
	protected ?string $shareToken;
	protected bool $isPageShare;
	protected bool $shareEditable;
	protected ?int $userPageOrder;
	protected ?bool $userShowRecentPages;

	public function __construct(Collective $collective,
		string $name,
		int $level = Member::LEVEL_MEMBER,
		?string $shareToken = null,
		bool $isPageShare = false,
		bool $shareEditable = false,
		?int $userPageOrder = Collective::defaultPageOrder,
		?bool $userShowRecentPages = Collective::defaultShowRecentPages) {
		$this->id = $collective->getId();
		$this->circleUniqueId = $collective->getCircleId();
		$this->emoji = $collective->getEmoji();
		$this->permissions = $collective->getPermissions();
		$this->trashTimestamp = $collective->getTrashTimestamp();
		$this->pageMode = $collective->getPageMode();
		$this->name = $name;
		$this->level = $level;
		$this->shareToken = $shareToken;
		$this->isPageShare = $isPageShare;
		$this->shareEditable = $shareEditable;
		$this->userPageOrder = $userPageOrder;
		$this->userShowRecentPages = $userShowRecentPages;
	}

	/**
	 * @return int
	 */
	public function getUserPermissions(): int {
		if ($this->level === Member::LEVEL_OWNER || $this->level === Member::LEVEL_ADMIN) {
			return Constants::PERMISSION_ALL;
		}

		if ($this->level === Member::LEVEL_MODERATOR) {
			return $this->getModeratorPermissions();
		}

		return $this->getMemberPermissions();
	}

	/**
	 * @param int $permission
	 *
	 * @return int
	 */
	private function getPermissionLevel(int $permission): int {
		if (($this->getMemberPermissions() | $permission) === $this->getMemberPermissions()) {
			return Member::LEVEL_MEMBER;
		}

		if (($this->getModeratorPermissions() | $permission) === $this->getModeratorPermissions()) {
			return Member::LEVEL_MODERATOR;
		}

		return Member::LEVEL_ADMIN;
	}

	/**
	 * @return int
	 */
	public function getEditPermissionLevel(): int {
		return $this->getPermissionLevel(Collective::editPermissions);
	}

	/**
	 * @return int
	 */
	public function getSharePermissionLevel(): int {
		return $this->getPermissionLevel(Constants::PERMISSION_SHARE);
	}

	/**
	 * @return bool
	 */
	public function canEdit(): bool {
		return $this->level >= $this->getEditPermissionLevel();
	}

	/**
	 * @return bool
	 */
	public function canShare(): bool {
		return $this->level >= $this->getSharePermissionLevel();
	}

	/**
	 * @param int $userPageOrder
	 */
	public function setUserPageOrder(int $userPageOrder): void {
		if (!array_key_exists($userPageOrder, Collective::pageOrders)) {
			throw new \RuntimeException('Invalid userPageOrder value: ' . $userPageOrder);
		}
		$this->userPageOrder = $userPageOrder;
	}

	/**
	 * @param bool $userShowRecentPages
	 */
	public function setUserShowRecentPages(bool $userShowRecentPages): void {
		$this->userShowRecentPages = $userShowRecentPages;
	}

	/**
	 * @return array
	 */
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
