<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Provides access to the following user settings used by the collectives app.
 * - user_folder: Path where collectives are mounted in user home directory.
 */
class SettingsController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	private function validateGetUserSetting(string $setting): void {
		if ($setting === 'user_folder') {
			return;
		}

		throw new InvalidArgumentException('Unsupported setting ' . $setting);
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function validateSetUserSetting(string $setting, ?string $value): void {
		if ($value === null) {
			throw new InvalidArgumentException('Empty value for setting ' . $setting);
		}

		if ($setting === 'user_folder') {
			// No root folder, has to start with `/`, not allowed to end with `/`
			if ($value === '/'
				|| !(str_starts_with($value, '/'))
				|| str_ends_with($value, '/')) {
				throw new InvalidArgumentException('Invalid collectives folder path');
			}

			return;
		}

		throw new InvalidArgumentException('Unsupported setting ' . $setting);
	}

	/**
	 * Get a collectives user setting by key (defaults to empty string if key is unset)
	 *
	 * @param string $key The key to get
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Valid key, value returned
	 * 400: Invalid key
	 */
	#[NoAdminRequired]
	public function getUserSetting(string $key): DataResponse {
		try {
			$this->validateGetUserSetting($key);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse([$key => $this->config->getUserValue($this->userId, 'collectives', $key, '')]);
	}

	/**
	 * Set a collectives user setting by key
	 *
	 * @param string $key The key to set
	 * @param string $value The value
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Valid key set to value
	 * 400: Invalid key
	 */
	#[NoAdminRequired]
	public function setUserSetting(string $key, string $value): DataResponse {
		try {
			$this->validateSetUserSetting($key, $value);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		$this->config->setUserValue($this->userId, 'collectives', $key, $value);
		return new DataResponse([$key => $value]);
	}
}
