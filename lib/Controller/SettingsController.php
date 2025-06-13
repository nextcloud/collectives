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
use OCP\AppFramework\OCS\OCSBadRequestException;
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

	/**
	 * @throws OCSBadRequestException
	 */
	private function validateGetUserSetting(string $setting): void {
		if ($setting === 'user_folder') {
			return;
		}

		throw new OCSBadRequestException('Unsupported setting ' . $setting);
	}

	/**
	 * @throws OCSBadRequestException
	 */
	private function validateSetUserSetting(string $setting, ?string $value): void {
		if ($value === null) {
			throw new OCSBadRequestException('Empty value for setting ' . $setting);
		}

		if ($setting === 'user_folder') {
			// No root folder, has to start with `/`, not allowed to end with `/`
			if ($value === '/'
				|| !(str_starts_with($value, '/'))
				|| str_ends_with($value, '/')) {
				throw new OCSBadRequestException('Invalid collectives folder path');
			}

			return;
		}

		throw new OCSBadRequestException('Unsupported setting ' . $setting);
	}

	/**
	 * Get a collectives user setting by key (defaults to empty string if key is unset)
	 *
	 * @param string $key The key to get
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, string>, array{}>
	 * @throws OCSBadRequestException Invalid key
	 *
	 * 200: Valid key, value returned
	 */
	#[NoAdminRequired]
	public function getUserSetting(string $key): DataResponse {
		$this->validateGetUserSetting($key);
		return new DataResponse([$key => $this->config->getUserValue($this->userId, 'collectives', $key, '')]);
	}

	/**
	 * Set a collectives user setting by key
	 *
	 * @param string $key The key to set
	 * @param string $value The value
	 *
	 * @return DataResponse<Http::STATUS_OK, array<string, string>, array{}>
	 * @throws OCSBadRequestException Invalid key
	 *
	 * 200: Valid key set to value
	 */
	#[NoAdminRequired]
	public function setUserSetting(string $key, string $value): DataResponse {
		$this->validateSetUserSetting($key, $value);
		$this->config->setUserValue($this->userId, 'collectives', $key, $value);
		return new DataResponse([$key => $value]);
	}
}
