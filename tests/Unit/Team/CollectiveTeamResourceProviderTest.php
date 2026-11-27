<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Team;

use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\CacheListener;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCA\Collectives\Team\CollectiveTeamResourceProvider;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\NotPermittedException;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Teams\ITeamOwnedResourceProvider;
use Psr\Container\ContainerInterface;
use Test\TestCase;

class CollectiveTeamResourceProviderTest extends TestCase {
	private CollectiveMapper $mapper;
	private CollectiveService $service;
	private IUserManager $userManager;
	private IFactory $l10nFactory;
	private CollectiveTeamResourceProvider $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = $this->createMock(CollectiveMapper::class);
		$this->service = $this->createMock(CollectiveService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')->with('collectives.start.index')->willReturn('https://example.test/collectives/');
		$this->provider = new CollectiveTeamResourceProvider($this->mapper, $this->service, $urlGenerator, 'different-user', $this->userManager, $this->l10nFactory);
	}

	public function testApplicationRegistersOwnedProviderAsResourceProvider(): void {
		self::assertInstanceOf(ITeamOwnedResourceProvider::class, $this->provider);
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects(self::once())->method('registerTeamResourceProvider')->with(CollectiveTeamResourceProvider::class);
		$cacheListener = $this->createMock(CacheListener::class);
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->with(CacheListener::class)->willReturn($cacheListener);
		$app = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods(['getContainer'])->getMock();
		$app->method('getContainer')->willReturn($container);
		$app->register($context);
	}

	public function testNavigationUsesRequestedUserAndExistingResourceFormat(): void {
		$collective = new Collective();
		$collective->setId(42);
		$collective->setName('Team & friends');
		$collective->setEmoji('📚');
		$this->mapper->method('findByCircleId')->with('team-id')->willReturn($collective);
		$this->service->expects(self::once())->method('getCollective')->with(42, 'member')->willReturn($collective);
		$resources = $this->provider->getOwnedByTeam('team-id', 'member', false, null);
		self::assertCount(1, $resources);
		self::assertSame($this->provider, $resources[0]->getProvider());
		self::assertSame('42', $resources[0]->getId());
		self::assertSame('Team & friends', $resources[0]->getLabel());
		self::assertSame('https://example.test/collectives/Team%20%26%20friends', $resources[0]->getUrl());
		self::assertSame('📚', $resources[0]->getIconEmoji());
	}

	public function testNavigationWithoutCollective(): void {
		$this->mapper->method('findByCircleId')->willReturn(null);
		$this->service->expects(self::never())->method('getCollective');
		self::assertSame([], $this->provider->getOwnedByTeam('team-id', 'member', false, null));
	}

	public function testNavigationWithoutEmojiUsesThemeColoredProviderIcon(): void {
		$collective = new Collective();
		$collective->setId(42);
		$collective->setName('Team');
		$this->mapper->method('findByCircleId')->willReturn($collective);
		$this->service->method('getCollective')->willReturn($collective);
		$resource = $this->provider->getOwnedByTeam('team-id', 'member', false, null)[0]->jsonSerialize();

		self::assertNull($resource['iconEmoji']);
		$svg = new \DOMDocument();
		self::assertTrue($svg->loadXML($resource['provider']['icon']));
		$paths = $svg->getElementsByTagName('path');
		self::assertGreaterThan(0, $paths->length);
		foreach ($paths as $path) {
			self::assertContains($path->getAttribute('fill'), ['', 'currentColor']);
		}
	}

	public function testOnlyAdminCanCreateAndTrashedCollectiveBlocksCreation(): void {
		$this->mapper->expects(self::once())->method('findByCircleId')->with('team-id', true)->willReturn(new Collective());
		self::assertFalse($this->provider->canCreateResource('team-id', 'member', false, null));
		self::assertFalse($this->provider->canCreateResource('team-id', 'member', true, null));
	}

	public function testCreateForTeamReturnsId(): void {
		$this->mapper->method('findByCircleId')->with('team-id', true)->willReturn(null);
		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')->with('admin')->willReturn($user);
		$this->l10nFactory->method('getUserLanguage')->with($user)->willReturn('de');
		$collective = new Collective();
		$collective->setId(42);
		$this->service->expects(self::once())->method('createCollectiveForTeam')->with('team-id', 'admin', 'de')->willReturn($collective);
		self::assertSame('42', $this->provider->createResource('team-id', 'Team name', 'admin', true, null));
	}

	public function testCreateRejectsNonAdmin(): void {
		$this->service->expects(self::never())->method('createCollectiveForTeam');
		$this->expectException(NotPermittedException::class);
		$this->provider->createResource('team-id', 'Team name', 'member', false, null);
	}

	public function testCreateReportsConcurrentCreation(): void {
		$this->mapper->method('findByCircleId')->willReturn(null);
		$user = $this->createMock(IUser::class);
		$this->userManager->method('get')->willReturn($user);
		$this->l10nFactory->method('getUserLanguage')->willReturn('de');
		$this->service->method('createCollectiveForTeam')->willThrowException(new UnprocessableEntityException('Collective already exists.'));
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Collective already exists.');
		$this->provider->createResource('team-id', 'Team name', 'admin', true, null);
	}

	public function testResourceProviderOperationsUseExistingCollectiveBehavior(): void {
		$collective = new Collective();
		$collective->setId(42);
		$collective->setName('Team');
		$collective->setCircleId('team-id');
		$this->mapper->method('findByCircleId')->with('team-id')->willReturn($collective);
		$this->service->expects(self::exactly(3))->method('getCollective')->with(42, 'different-user')->willReturn($collective);

		self::assertSame('collectives', $this->provider->getId());
		self::assertSame('Collective', $this->provider->getName());
		self::assertNotEmpty($this->provider->getIconSvg());
		$resources = $this->provider->getSharedWith('team-id');
		self::assertCount(1, $resources);
		self::assertSame($this->provider, $resources[0]->getProvider());
		self::assertSame('42', $resources[0]->getId());
		self::assertSame('https://example.test/collectives/Team', $resources[0]->getUrl());
		self::assertTrue($this->provider->isSharedWithTeam('team-id', '42'));
		self::assertSame(['team-id'], $this->provider->getTeamsForResource('42'));
	}
}
