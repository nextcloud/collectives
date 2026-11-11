<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Dashboard;

use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\CacheListener;
use OCA\Collectives\Dashboard\RecentPagesTeamWidget;
use OCA\Collectives\Dashboard\RecentPagesWidget;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Model\RecentPage;
use OCA\Collectives\Service\RecentPagesService;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Dashboard\ITeamWidget;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class RecentPagesTeamWidgetTest extends TestCase {
	private RecentPagesService&MockObject $service;
	private IURLGenerator&MockObject $urls;
	private IUserSession&MockObject $userSession;
	private IL10N&MockObject $l10n;
	private RecentPagesTeamWidget $widget;

	protected function setUp(): void {
		parent::setUp();
		if (!interface_exists(ITeamWidget::class)) {
			self::markTestSkipped('Team widgets require Nextcloud 36.');
		}

		$this->service = $this->createMock(RecentPagesService::class);
		$this->urls = $this->createMock(IURLGenerator::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnArgument(0);
		$this->widget = new RecentPagesTeamWidget($this->l10n, $this->urls, $this->userSession, $this->service);
	}

	public function testSharesPersonalWidgetMetadata(): void {
		$personal = new RecentPagesWidget($this->l10n, $this->urls, $this->userSession, $this->service);
		self::assertSame('collectives-recent-pages', $this->widget->getId());
		self::assertSame($personal->getId(), $this->widget->getId());
		self::assertSame($personal->getTitle(), $this->widget->getTitle());
		self::assertSame($personal->getOrder(), $this->widget->getOrder());
		self::assertSame($personal->getReloadInterval(), $this->widget->getReloadInterval());
		self::assertSame($personal->getIconClass(), $this->widget->getIconClass());
	}

	public function testRegistersOnlyTheTeamCapableWidget(): void {
		$listener = $this->createMock(CacheListener::class);
		$listener->expects(self::once())->method('listen');
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(CacheListener::class)->willReturn($listener);
		$application = $this->getMockBuilder(Application::class)
			->disableOriginalConstructor()->onlyMethods(['getContainer'])->getMock();
		$application->method('getContainer')->willReturn($container);
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects(self::once())->method('registerDashboardWidget')->with(RecentPagesTeamWidget::class);

		$application->register($context);
	}

	public function testEnabledAndUrlShareCollectiveLookup(): void {
		$collective = new Collective();
		$collective->setId(42);
		$collective->setSlug('team-handbook');
		$collective->setName('Team handbook');
		$this->service->expects(self::once())->method('getTeamCollectives')
			->with('team-a', 'alice')->willReturn([$collective, new Collective()]);
		$this->service->expects(self::never())->method('forTeam');
		$this->urls->expects(self::once())->method('linkToRoute')
			->with('collectives.start.indexPath', ['path' => 'team-handbook-42'])
			->willReturn('/collectives/team-handbook-42');

		self::assertTrue($this->widget->isEnabledForTeam('team-a', 'alice'));
		self::assertSame('/collectives/team-handbook-42', $this->widget->getTeamUrl('team-a', 'alice'));
	}

	public function testUrlFallsBackToCollectiveName(): void {
		$collective = new Collective();
		$collective->setName('Team handbook');
		$this->service->method('getTeamCollectives')->willReturn([$collective]);
		$this->urls->expects(self::once())->method('linkToRoute')
			->with('collectives.start.indexPath', ['path' => 'Team handbook'])
			->willReturn('/collectives/Team%20handbook');

		self::assertSame('/collectives/Team%20handbook', $this->widget->getTeamUrl('team-a', 'alice'));
	}

	public function testNoCollectiveDisablesWidgetAndCachesEmptyLookup(): void {
		$this->service->expects(self::once())->method('getTeamCollectives')
			->with('team-a', 'alice')->willReturn([]);
		$this->service->expects(self::never())->method('forTeam');
		$this->urls->expects(self::never())->method('linkToRoute');

		self::assertFalse($this->widget->isEnabledForTeam('team-a', 'alice'));
		self::assertNull($this->widget->getTeamUrl('team-a', 'alice'));
	}

	public function testCollectiveCacheIsScopedToTeamAndUser(): void {
		$this->service->expects(self::exactly(3))->method('getTeamCollectives')
			->willReturnCallback(static fn (string $teamId, string $userId): array
				=> $teamId === 'team-a' && $userId === 'alice' ? [new Collective()] : []);

		self::assertTrue($this->widget->isEnabledForTeam('team-a', 'alice'));
		self::assertFalse($this->widget->isEnabledForTeam('team-b', 'alice'));
		self::assertFalse($this->widget->isEnabledForTeam('team-a', 'bob'));
		self::assertTrue($this->widget->isEnabledForTeam('team-a', 'alice'));
	}

	public function testTeamItemsUseSharedFormattingAndExplicitActor(): void {
		$page = new RecentPage();
		$page->setTitle('Handbook')->setCollectiveNameWithEmoji('Team')
			->setPagePath('Guides')->setPageUrl('/collectives/handbook')
			->setEmoji('A')->setTimestamp(123);
		$this->service->expects(self::once())->method('forTeam')
			->with('team-a', 'alice', 3)->willReturn([$page]);
		$this->service->expects(self::never())->method('forUser');
		$this->userSession->expects(self::never())->method('getUser');

		$result = $this->widget->getTeamItems('team-a', 'alice', null, 3);
		self::assertSame('No recent pages in this collective', $result->getEmptyContentMessage());
		self::assertCount(1, $result->getItems());
		$item = $result->getItems()[0];
		self::assertSame('Handbook', $item->getTitle());
		self::assertSame('Team - Guides', $item->getSubtitle());
		self::assertSame('/collectives/handbook', $item->getLink());
		self::assertSame('123', $item->getSinceId());
		self::assertStringStartsWith('data:image/svg+xml;base64,', $item->getIconUrl());
		self::assertStringContainsString('>A</text>', base64_decode(substr($item->getIconUrl(), 26)));
	}

	public function testTeamEmptyMessageAndDefaultLimit(): void {
		$this->service->expects(self::once())->method('forTeam')
			->with('team-a', 'alice', 7)->willReturn([]);

		$result = $this->widget->getTeamItems('team-a', 'alice');
		self::assertSame([], $result->getItems());
		self::assertSame('No recent pages in this collective', $result->getEmptyContentMessage());
	}

	public function testPersonalItemsKeepSessionUserAndFormatting(): void {
		$user = $this->createMock(IUser::class);
		$this->userSession->method('getUser')->willReturn($user);
		$page = new RecentPage();
		$page->setTitle('Landing page')->setCollectiveNameWithEmoji('Team')
			->setPagePath('')->setPageUrl('/collectives/team')->setTimestamp(123);
		$this->service->expects(self::once())->method('forUser')->with($user, 3)->willReturn([$page]);
		$this->service->expects(self::never())->method('forTeam');

		$result = $this->widget->getItemsV2('alice', null, 3);
		self::assertSame('Add a collective', $result->getEmptyContentMessage());
		self::assertCount(1, $result->getItems());
		self::assertSame('Team', $result->getItems()[0]->getSubtitle());
		self::assertSame('Landing page', $result->getItems()[0]->getTitle());
		self::assertSame('/collectives/team', $result->getItems()[0]->getLink());
		self::assertStringStartsWith('data:image/svg+xml;base64,', $result->getItems()[0]->getIconUrl());
	}

	public function testPersonalItemsWithoutSessionUser(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->service->expects(self::never())->method('forUser');

		$result = $this->widget->getItemsV2('alice');
		self::assertSame([], $result->getItems());
		self::assertSame('', $result->getEmptyContentMessage());
	}
}
