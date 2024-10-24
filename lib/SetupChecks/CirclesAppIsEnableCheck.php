<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\SetupChecks;

use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class CirclesAppIsEnableCheck implements ISetupCheck {
	public function __construct(
		private IL10N $l10n,
		private IAppManager $appManager,
	) {
	}


	public function getCategory(): string {
		return 'app';
	}

	public function getName(): string {
		return $this->l10n->t('Teams App Enabled');
	}

	public function run(): SetupResult {
		if ($this->appManager->isEnabledForUser('circles')) {
			return SetupResult::success($this->l10n->t('Teams app is enabled'));
		}

		return SetupResult::error($this->l10n->t('The teams app is not enabled, but is required for Collectives to work.'));
	}
}
