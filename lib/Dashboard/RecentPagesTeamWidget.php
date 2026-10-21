<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Dashboard;

use OCA\Collectives\Service\RecentPagesService;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\ITeamWidget;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;

class RecentPagesTeamWidget implements ITeamWidget, IIconWidget {
	public function __construct(
		private RecentPagesWidget $personalWidget,
		private RecentPagesService $recentPagesService,
		private IL10N $l10n,
	) {
	}

	public function getTeamItems(string $teamId, string $userId, ?string $since = null, int $limit = 7): ?WidgetItems {
		$pages = $this->recentPagesService->forTeam($teamId, $userId, $limit);
		return $pages === null ? null : $this->personalWidget->formatItems($pages, $this->l10n->t('No recent pages in this collective'));
	}

	public function getId(): string {
		return 'collectives-team-recent-pages';
	}

	public function getTitle(): string {
		return $this->personalWidget->getTitle();
	}

	public function getOrder(): int {
		return $this->personalWidget->getOrder();
	}

	public function getIconClass(): string {
		return $this->personalWidget->getIconClass();
	}

	public function getIconUrl(): string {
		return $this->personalWidget->getIconUrl();
	}

	public function getUrl(): ?string {
		return null;
	}

	public function load(): void {
	}
}
