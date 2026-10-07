<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Controller;

use OCA\Collectives\Controller\StaticSiteGeneratorController;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

// TODO(Publish): Change tests when ext service provides a test endpoint to validate url and shared secret
class StaticSiteGeneratorControllerTest extends TestCase {
	private StaticSiteGeneratorClient&MockObject $client;
	private StaticSiteGeneratorController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->client = $this->createMock(StaticSiteGeneratorClient::class);
		$this->controller = new StaticSiteGeneratorController(
			'collectives',
			$this->createMock(IRequest::class),
			$this->client,
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
}
