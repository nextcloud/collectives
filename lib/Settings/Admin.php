<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Settings;

use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\ConfigLexicon;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public const TOKEN_PLACEHOLDER = '********';

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IInitialState $initialState,
	) {
	}

	public function getForm(): TemplateResponse {
		$parameters = [
			'default_user_folder' => $this->appConfig->getValueString('collectives', ConfigLexicon::DEFAULT_USER_FOLDER, ''),
			'publish_enabled' => $this->appConfig->getValueString('collectives', ConfigLexicon::PUBLISH_ENABLED, ConfigLexicon::PUBLISH_ENABLED_DEFAULT) === 'true',
			'static_site_generator_url' => $this->appConfig->getValueString('collectives', ConfigLexicon::STATIC_SITE_GENERATOR_URL),
			// The token itself must not be rendered into the page, so a set token is replaced by a placeholder
			'static_site_generator_token' => $this->appConfig->getValueString('collectives', ConfigLexicon::STATIC_SITE_GENERATOR_TOKEN) === '' ? '' : self::TOKEN_PLACEHOLDER,
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
