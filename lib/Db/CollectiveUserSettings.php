<?php

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * Class CollectiveShare
 * @method int getId()
 * @method void setId(int $value)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method int getPageOrder()
 */
class CollectiveUserSettings extends Entity implements JsonSerializable {
	/** @var int */
	protected $collectiveId;

	/** @var string */
	protected $userId;

	/** @var int */
	protected $pageOrder;

	/**
	 * @param int $pageOrder
	 */
	public function setPageOrder(int $pageOrder): void {
		if (!array_key_exists($pageOrder, Collective::pageOrders)) {
			throw new \RuntimeException('Invalid pageOrder value: ' . $pageOrder);
		}
		$this->pageOrder = $pageOrder;
		$this->markFieldUpdated('pageOrder');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'collectiveId' => (int)$this->collectiveId,
			'userId' => $this->userId,
			'pageOrder' => $this->pageOrder,
		];
	}
}
