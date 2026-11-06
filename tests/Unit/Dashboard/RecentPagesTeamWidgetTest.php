<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Dashboard;

use OCA\Collectives\Dashboard\RecentPagesTeamWidget;
use OCA\Collectives\Dashboard\RecentPagesWidget;
use OCA\Collectives\Service\RecentPagesService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class RecentPagesTeamWidgetTest extends TestCase {
	public function testMissingCollectiveIsUnavailableAndNotPersonal(): void {
		$service = $this->createMock(RecentPagesService::class);
		$service->expects(self::once())->method('forTeam')->with('team-a', 'alice', 3)->willReturn(null);
		$personal = $this->createMock(RecentPagesWidget::class);
		$personal->expects(self::never())->method('getItemsV2');
		$personal->expects(self::never())->method('formatItems');
		$widget = new RecentPagesTeamWidget($personal, $service, $this->createMock(IL10N::class));
		self::assertNull($widget->getTeamItems('team-a', 'alice', null, 3));
		self::assertNotInstanceOf(IAPIWidgetV2::class, $widget);
		self::assertNull($widget->getUrl());
		self::assertSame('collectives-team-recent-pages', $widget->getId());
	}

	public function testEmptyCollectiveUsesTeamEmptyState(): void {
		$service = $this->createMock(RecentPagesService::class);
		$service->method('forTeam')->willReturn([]);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$personal = $this->createMock(RecentPagesWidget::class);
		$personal->expects(self::once())->method('formatItems')->with([], 'No recent pages in this collective')->willReturn(new WidgetItems([], 'No recent pages in this collective'));
		$widget = new RecentPagesTeamWidget($personal, $service, $l10n);
		self::assertSame([], $widget->getTeamItems('team-a', 'alice')->getItems());
	}
}
