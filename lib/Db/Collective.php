<?php

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCA\Circles\Model\Member;
use OCP\AppFramework\Db\Entity;
use OCP\Constants;

/**
 * Class Collective
 * @method int getId()
 * @method void setId(int $value)
 * @method string getCircleUniqueId()
 * @method void setCircleUniqueId(string $circleUniqueId)
 * @method int getPermissions()
 * @method void setPermissions(int $permissions)
 * @method string getEmoji()
 * @method void setEmoji(string $emoji)
 * @method int|null getTrashTimestamp()
 * @method void setTrashTimestamp(?int $trashTimestamp)
 */
class Collective extends Entity implements JsonSerializable {
	/** @var int */
	public const defaultPermissions =
		Constants::PERMISSION_ALL * 100 + // Moderator
		Constants::PERMISSION_ALL;        // Member

	public const editPermissions =
		Constants::PERMISSION_UPDATE +
		Constants::PERMISSION_CREATE +
		Constants::PERMISSION_DELETE;

	public const pageOrders = [
		0 => 'byOrder',
		1 => 'byTimestamp',
		2 => 'byTitle',
	];
	public const defaultPageOrder = 0;

	protected ?string $circleUniqueId = null;
	protected int $permissions = self::defaultPermissions;
	protected ?string $emoji = null;
	protected ?int $trashTimestamp = null;

	/**
	 * @return string|null
	 */
	public function getCircleId(): ?string {
		return $this->getCircleUniqueId();
	}

	/**
	 * @param string $circleId
	 */
	public function setCircleId(string $circleId): void {
		$this->setCircleUniqueId($circleId);
	}

	/**
	 * @return int
	 */
	public function getModeratorPermissions(): int {
		// moderator permissions are stored in thousands and hundreds
		return intdiv($this->permissions, 100) % 100;
	}

	/**
	 * @param int $permissions
	 */
	public function setModeratorPermissions(int $permissions): void {
		// moderator permissions are stored in thousands and hundreds
		$this->setPermissions(
			$permissions * 100 +          // Moderator
			$this->getMemberPermissions() // Member
		);
	}

	/**
	 * @return int
	 */
	public function getMemberPermissions(): int {
		// member permissions are stored in tens and ones
		return $this->permissions % 100;
	}

	/**
	 * @param int $permissions
	 */
	public function setMemberPermissions(int $permissions): void {
		// member permissions are stored in tens and ones
		$this->setPermissions(
			$this->getModeratorPermissions() * 100 + // Moderator
			$permissions                             // Member
		);
	}

	/**
	 * @param int        $level
	 * @param int        $permission
	 */
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

	/**
	 * @return bool
	 */
	public function isTrashed(): bool {
		return (bool)$this->getTrashTimestamp();
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'circleId' => $this->circleUniqueId,
			'permissions' => $this->permissions,
			'emoji' => $this->emoji,
			'trashTimestamp' => $this->trashTimestamp
		];
	}
}
