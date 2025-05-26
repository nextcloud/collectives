<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use JsonException;
use JsonSerializable;

use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $value)
 * @method int getCollectiveId()
 * @method void setCollectiveId(int $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method string getSettings()
 */
class CollectiveUserSettings extends Entity implements JsonSerializable {
	/** @var array */
	public const supportedSettings = [
		'page_order',
		'show_members',
		'show_recent_pages',
		'favorite_pages',
	];

	protected ?int $collectiveId = null;
	protected ?string $userId = null;
	protected int $pageOrder = Collective::defaultPageOrder;
	protected string $settings = '{}';

	/**
	 * @throws NotPermittedException
	 */
	private static function checkSetting(string $setting): void {
		if (!in_array($setting, self::supportedSettings)) {
			throw new NotPermittedException('Invalid collectives user setting');
		}
	}

	/**
	 * @return mixed|null
	 * @throws NotPermittedException
	 * @throws JsonException
	 */
	public function getSetting(string $setting): mixed {
		self::checkSetting($setting);
		return json_decode($this->settings, true, 512, JSON_THROW_ON_ERROR)[$setting] ?? null;
	}

	/**
	 * @throws NotPermittedException
	 * @throws JsonException
	 */
	private function setSetting(string $setting, mixed $value): void {
		self::checkSetting($setting);
		$settings = json_decode($this->settings, true, 512, JSON_THROW_ON_ERROR) ?? [];
		$settings[$setting] = $value;
		$this->settings = json_encode($settings, JSON_THROW_ON_ERROR);
		$this->markFieldUpdated('settings');
	}

	/**
	 * @throws NotPermittedException
	 * @throws JsonException
	 */
	public function setPageOrder(int $pageOrder): void {
		if (!array_key_exists($pageOrder, Collective::pageOrders)) {
			throw new NotPermittedException('Invalid pageOrder value: ' . $pageOrder);
		}
		$this->setSetting('page_order', $pageOrder);
	}

	/**
	 * @throws NotPermittedException
	 * @throws JsonException
	 */
	public function setShowMembers(bool $showMembers): void {
		$this->setSetting('show_members', $showMembers);
	}

	/**
	 * @throws NotPermittedException
	 * @throws JsonException
	 */
	public function setShowRecentPages(bool $showRecentPages): void {
		$this->setSetting('show_recent_pages', $showRecentPages);
	}

	/**
	 * @throws NotPermittedException
	 * @throws JsonException
	 */
	public function setFavoritePages(array $favoritePages): void {
		if ($favoritePages !== array_filter($favoritePages, 'is_int')) {
			throw new NotPermittedException('Invalid favorite pages value.');
		}
		$this->setSetting('favorite_pages', $favoritePages);
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
