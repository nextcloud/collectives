<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Controller;

use OCA\Collectives\Controller\StaticSiteArchiveController;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class StaticSiteArchiveControllerTest extends TestCase {
	private StaticSiteService&MockObject $staticSiteService;
	private StaticSiteGeneratorClient&MockObject $staticSiteGeneratorClient;
	private StaticSiteArchiveController $controller;

	private string $staticSiteId = '01990000-0000-7000-8000-000000000000';

	protected function setUp(): void {
		parent::setUp();

		$request = $this->createMock(IRequest::class);
		$request->method('getHeader')->with('Authorization')->willReturn('Bearer secret');
		$this->staticSiteService = $this->createMock(StaticSiteService::class);
		$this->staticSiteGeneratorClient = $this->createMock(StaticSiteGeneratorClient::class);

		$this->controller = new StaticSiteArchiveController('collectives', $request, $this->staticSiteService, $this->staticSiteGeneratorClient);
	}

	public function testDownloadRejectsInvalidTokenAndThrottles(): void {
		$this->staticSiteGeneratorClient->method('isValidToken')->with('Bearer secret')->willReturn(false);
		$this->staticSiteService->expects($this->never())->method('getArchive');

		$response = $this->controller->download($this->staticSiteId);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertTrue($response->isThrottled());
	}

	public function testDownloadReturnsNotFoundForUnknownArchive(): void {
		$this->staticSiteGeneratorClient->method('isValidToken')->willReturn(true);
		$this->staticSiteService->method('getArchive')
			->with($this->staticSiteId)
			->willThrowException(new NotFoundException('Static site archive not found'));

		$response = $this->controller->download($this->staticSiteId);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertFalse($response->isThrottled());
	}

	public function testDownloadReturnsArchiveAsAttachment(): void {
		$archive = $this->createMock(ISimpleFile::class);
		$archive->method('getName')->willReturn($this->staticSiteId . '.tar.gz');
		$this->staticSiteGeneratorClient->method('isValidToken')->willReturn(true);
		$this->staticSiteService->method('getArchive')->with($this->staticSiteId)->willReturn($archive);

		$response = $this->controller->download($this->staticSiteId);

		$this->assertInstanceOf(FileDisplayResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$headers = $response->getHeaders();
		$this->assertSame('application/gzip', $headers['Content-Type']);
		$this->assertSame('attachment; filename="' . $this->staticSiteId . '.tar.gz"', $headers['Content-Disposition']);
	}
}
