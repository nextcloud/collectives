<?php

declare(strict_types=1);

namespace OCA\Collectives\Db;

use JsonSerializable;

use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Class CollectiveShare
 * @method int getId()
 * @method void setId(int $value)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method array getSettings()
 */
class CollectiveUserSettings extends Entity implements JsonSerializable {
	/** @var array */
	public const supportedSettings = [
		'page_order',
	];

	protected ?int $collectiveId = null;
	protected ?string $userId = null;
	protected int $pageOrder = Collective::defaultPageOrder;
	protected ?array $settings = null;

	public function __construct() {
		$this->addType('settings', Types::JSON);
	}

	/**
	 * @param string $setting
	 *
	 * @throws NotPermittedException
	 */
	private static function checkSetting(string $setting): void {
		if (!in_array($setting, self::supportedSettings)) {
			throw new NotPermittedException('Invalid collectives user setting');
		}
	}

	/**
	 * @param string $setting
	 *
	 * @return mixed|null
	 * @throws NotPermittedException
	 */
	public function getSetting(string $setting) {
		self::checkSetting($setting);
		return $this->settings[$setting] ?? null;
	}

	/**
	 * @param string $setting
	 * @param mixed  $value
	 *
	 * @throws NotPermittedException
	 */
	private function setSetting(string $setting, $value): void {
		self::checkSetting($setting);
		$this->settings[$setting] = $value;
		$this->markFieldUpdated('settings');
	}

	/**
	 * @param int $pageOrder
	 *
	 * @throws NotPermittedException
	 */
	public function setPageOrder(int $pageOrder): void {
		if (!array_key_exists($pageOrder, Collective::pageOrders)) {
			throw new NotPermittedException('Invalid pageOrder value: ' . $pageOrder);
		}
		$this->setSetting('page_order', $pageOrder);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'collectiveId' => (int)$this->collectiveId,
			'userId' => $this->userId,
			'settings' => $this->settings,
		];
	}
}
