<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Dashboard;

use OCA\Collectives\Db\Collective;
use OCP\Dashboard\ITeamWidget;
use OCP\Dashboard\Model\WidgetItems;

class RecentPagesTeamWidget extends RecentPagesWidget implements ITeamWidget {
	/** @var array<string, list<Collective>> */
	private array $teamCollectives = [];

	public function isEnabledForTeam(string $teamId, string $userId): bool {
		return $this->getTeamCollectives($teamId, $userId) !== [];
	}

	public function getTeamUrl(string $teamId, string $userId): ?string {
		$collective = $this->getTeamCollectives($teamId, $userId)[0] ?? null;
		if ($collective === null) {
			return null;
		}

		$path = $collective->getSlug()
			? $collective->getSlug() . '-' . $collective->getId()
			: $collective->getName();

		return $this->urlGenerator->linkToRoute('collectives.start.indexPath', ['path' => $path]);
	}

	public function getTeamItems(string $teamId, string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		return $this->formatItems(
			$this->recentPagesService->forTeam($teamId, $userId, $limit),
			$this->l10n->t('No recent pages in this collective'),
		);
	}

	/** @return list<Collective> */
	private function getTeamCollectives(string $teamId, string $userId): array {
		$key = "$teamId:$userId";
		return $this->teamCollectives[$key] ??= $this->recentPagesService->getTeamCollectives($teamId, $userId);
	}
}
