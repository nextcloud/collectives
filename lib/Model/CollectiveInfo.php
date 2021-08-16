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
 */
class CollectiveInfo extends Collective {
	/** @var string */
	protected $name;

	/** @var int */
	protected $level;

	public function __construct(Collective $collective, string $name, int $level = Member::LEVEL_MEMBER) {
		$this->id = $collective->getId();
		$this->circleUniqueId = $collective->getCircleId();
		$this->conversationToken = $collective->getConversationToken();
		$this->emoji = $collective->getEmoji();
		$this->trashTimestamp = $collective->getTrashTimestamp();
		$this->name = $name;
		$this->level = $level;
	}

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'circleId' => $this->circleUniqueId,
			'conversationToken' => $this->conversationToken,
			'emoji' => $this->emoji,
			'trashTimestamp' => $this->trashTimestamp,
			'name' => $this->name,
			'level' => $this->level
		];
	}
}
