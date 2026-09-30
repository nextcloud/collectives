<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Controller;

use OCA\Collectives\Controller\StaticSiteGeneratorController;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

// TODO(Publish): Change tests when ext service provides a test endpoint to validate url and shared secret
class StaticSiteGeneratorControllerTest extends TestCase {
	private StaticSiteGeneratorClient&MockObject $client;
	private StaticSiteService&MockObject $staticSiteService;
	private StaticSiteGeneratorController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->client = $this->createMock(StaticSiteGeneratorClient::class);
		$this->staticSiteService = $this->createMock(StaticSiteService::class);
		$this->controller = new StaticSiteGeneratorController(
			'collectives',
			$this->createMock(IRequest::class),
			$this->client,
			$this->staticSiteService,
			$this->createMock(LoggerInterface::class),
		);
	}

	public function testHealth(): void {
		$this->client->expects(self::once())->method('checkHealth');

		$response = $this->controller->health();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['status' => 'ok'], $response->getData());
	}

	public function testHealthFailed(): void {
		$this->client->method('checkHealth')
			->willThrowException(new StaticSiteGeneratorException('The static site generator is not reachable'));

		$response = $this->controller->health();

		self::assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
		self::assertSame(['message' => 'The static site generator is not reachable'], $response->getData());
	}

	public function testUpdateStatusReturnsStaticSite(): void {
		$staticSite = new StaticSite();
		$this->staticSiteService->method('updateStatus')
			->with('01990000-0000-7000-8000-000000000000', 'published', ['publish_url' => 'https://example.org'])
			->willReturn($staticSite);

		$response = $this->controller->updateStatus('01990000-0000-7000-8000-000000000000', 'published', ['publish_url' => 'https://example.org']);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame($staticSite, $response->getData());
	}

	public function testUpdateStatusWithUnknownStaticSiteThrowsNotFound(): void {
		$this->staticSiteService->method('updateStatus')
			->willThrowException(new NotFoundException('Static site not found'));

		$this->expectException(OCSNotFoundException::class);
		$this->controller->updateStatus('unknown', 'published');
	}
}
