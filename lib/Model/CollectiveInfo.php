<?php

namespace OCA\Collectives\Model;

use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;

/**
 * Class CollectiveInfo
 * @method string getName()
 * @method void setName(string $value)
 * @method int getLevel()
 * @method void setLevel(int $value)
 * @method int getShareToken()
 * @method void setShareToken(string $value)
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
		$this->trashTimestamp = $collective->getTrashTimestamp();
		$this->name = $name;
		$this->level = $level;
		$this->shareToken = $shareToken;
	}

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'circleId' => $this->circleUniqueId,
			'emoji' => $this->emoji,
			'trashTimestamp' => $this->trashTimestamp,
			'name' => $this->name,
			'level' => $this->level,
			'shareToken' => $this->shareToken,
		];
	}
}
