<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\SetupChecks;

use OCA\Collectives\Service\SearchService;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class PDOSQLiteDriverIsEnableCheck implements ISetupCheck {
	public function __construct(
		private IL10N $l10n,
		private SearchService $searchService,
	) {
	}


	public function getCategory(): string {
		return 'database';
	}

	public function getName(): string {
		return $this->l10n->t('PDO SQLite driver');
	}

	public function run(): SetupResult {
		if ($this->searchService->areDependenciesMet()) {
			return SetupResult::success($this->l10n->t('PDO SQLite driver is enabled, full text search of page content is available.'));
		}

		return SetupResult::error(
			$this->l10n->t('Collectives app is enabled, but PDO SQLite driver is missing. Please install it to enable full text search of the page content.')
		);
	}
}
