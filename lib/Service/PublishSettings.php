<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCP\IAppConfig;

class PublishSettings {
	private const CONFIG_APP = 'collectives';
	private const CONFIG_KEY_PUBLISH_ENABLED = 'publish_enabled';
	private const DEFAULT_PUBLISH_ENABLED = 'false';

	public function __construct(private readonly IAppConfig $appConfig) {
	}

	public function isPublishEnabled(): bool {
		return $this->appConfig->getValueString(
			self::CONFIG_APP,
			self::CONFIG_KEY_PUBLISH_ENABLED,
			self::DEFAULT_PUBLISH_ENABLED
		) === 'true';
	}
}
