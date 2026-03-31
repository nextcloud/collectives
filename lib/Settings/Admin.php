<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Settings;

use OCA\Collectives\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		$parameters = [
			'default_user_folder' => $this->appConfig->getValueString('collectives', 'default_user_folder', ''),
		];
		$this->initialState->provideInitialState('adminSettings', $parameters);

		Util::addStyle(Application::APP_NAME, Application::APP_NAME . '-settings-admin');
		Util::addScript(Application::APP_NAME, Application::APP_NAME . '-settings-admin');
		return new TemplateResponse(Application::APP_NAME, 'settings-admin', renderAs: '');
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 90;
	}
}
