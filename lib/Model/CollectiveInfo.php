<?php

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
 * @method int getShareToken()
 * @method void setShareToken(string $shareToken)
 */
class CollectiveInfo extends Collective {
	/** @var string */
	protected $name;

	/** @var int */
	protected $level;

	/** @var string */
	protected $shareToken;

	public function __construct(Collective $collective,
								string $name,
								int $level = Member::LEVEL_MEMBER,
								string $shareToken = null) {
		$this->id = $collective->getId();
		$this->circleUniqueId = $collective->getCircleId();
		$this->emoji = $collective->getEmoji();
		$this->permissions = $collective->getPermissions();
		$this->trashTimestamp = $collective->getTrashTimestamp();
		$this->name = $name;
		$this->level = $level;
		$this->shareToken = $shareToken;
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
		return$this->getPermissionLevel(Collective::editPermissions);
	}

	/**
	 * @return int
	 */
	public function getSharePermissionLevel(): int {
		return $this->getPermissionLevel(Constants::PERMISSION_SHARE);
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
			'name' => $this->name,
			'level' => $this->level,
			'editPermissionLevel' => $this->getEditPermissionLevel(),
			'sharePermissionLevel' => $this->getSharePermissionLevel(),
			'shareToken' => $this->shareToken,
		];
	}
}
