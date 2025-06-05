<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use JsonException;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettings;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCP\DB\Exception;

class CollectiveUserSettingsService {
	public function __construct(
		private CollectiveUserSettingsMapper $collectiveUserSettingsMapper,
		private CollectiveMapper $collectiveMapper,
	) {
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function initSettings(int $collectiveId, string $userId): CollectiveUserSettings {
		if ($this->collectiveMapper->findByIdAndUser($collectiveId, $userId) === null) {
			throw new NotFoundException('Collective not found: ' . $collectiveId);
		}

		$settings = $this->collectiveUserSettingsMapper->findByCollectiveAndUser($collectiveId, $userId);
		if ($settings === null) {
			$settings = new CollectiveUserSettings();
			$settings->setCollectiveId($collectiveId);
			$settings->setUserId($userId);
		}
		return $settings;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setPageOrder(int $collectiveId, string $userId, int $pageOrder): void {
		$settings = $this->initSettings($collectiveId, $userId);
		$settings->setPageOrder($pageOrder);

		try {
			$this->collectiveUserSettingsMapper->insertOrUpdate($settings);
		} catch (Exception $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setShowMembers(int $collectiveId, string $userId, bool $showMembers): void {
		$settings = $this->initSettings($collectiveId, $userId);
		$settings->setShowMembers($showMembers);

		try {
			$this->collectiveUserSettingsMapper->insertOrUpdate($settings);
		} catch (Exception $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setShowRecentPages(int $collectiveId, string $userId, bool $showRecentPages): void {
		$settings = $this->initSettings($collectiveId, $userId);
		$settings->setShowRecentPages($showRecentPages);

		try {
			$this->collectiveUserSettingsMapper->insertOrUpdate($settings);
		} catch (Exception $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setFavoritePages(int $collectiveId, string $userId, string $favoritePages): void {
		// Expect an array of
		try {
			$favoritePagesArray = json_decode($favoritePages, false, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new NotPermittedException('Unsupported favorite pages format (stringified array expected): ' . $favoritePages);
		}
		if (!is_array($favoritePagesArray)) {
			throw new NotPermittedException('Unsupported favorite pages format (stringified array expected): ' . $favoritePages);
		}
		$settings = $this->initSettings($collectiveId, $userId);
		$settings->setFavoritePages($favoritePagesArray);

		try {
			$this->collectiveUserSettingsMapper->insertOrUpdate($settings);
		} catch (Exception $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
	}
}
