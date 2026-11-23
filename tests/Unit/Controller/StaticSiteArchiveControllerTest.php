<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Controller;

use OCA\Collectives\Controller\StaticSiteArchiveController;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\StaticSiteBuildNotFoundException;
use OCA\Collectives\Service\StaticSiteService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class StaticSiteArchiveControllerTest extends TestCase {
	private StaticSiteService&MockObject $staticSiteService;
	private StaticSiteArchiveController $controller;

	private string $staticSiteId = '01990000-0000-7000-8000-000000000000';
	private string $buildId = '01990000-0000-7000-8000-000000000001';

	protected function setUp(): void {
		parent::setUp();

		$this->staticSiteService = $this->createMock(StaticSiteService::class);

		$this->controller = new StaticSiteArchiveController('collectives', $this->createMock(IRequest::class), $this->staticSiteService);
	}

	public function testDownloadReturnsNotFoundAndThrottlesForUnknownStaticSite(): void {
		$this->staticSiteService->method('getArchive')
			->with($this->staticSiteId, $this->buildId)
			->willThrowException(new NotFoundException('Static site not found'));

		$response = $this->controller->download($this->staticSiteId, $this->buildId);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertTrue($response->isThrottled());
	}

	public function testDownloadReturnsNotFoundWithoutThrottlingForOutdatedBuild(): void {
		$this->staticSiteService->method('getArchive')
			->willThrowException(new StaticSiteBuildNotFoundException('Build is not the current one of the static site'));

		$response = $this->controller->download($this->staticSiteId, $this->buildId);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertFalse($response->isThrottled());
	}

	public function testDownloadReturnsArchiveAsAttachment(): void {
		$archive = $this->createMock(ISimpleFile::class);
		$archive->method('getName')->willReturn($this->buildId . '.tar.gz');
		$this->staticSiteService->method('getArchive')->with($this->staticSiteId, $this->buildId)->willReturn($archive);

		$response = $this->controller->download($this->staticSiteId, $this->buildId);

		$this->assertInstanceOf(FileDisplayResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$headers = $response->getHeaders();
		$this->assertSame('application/gzip', $headers['Content-Type']);
		$this->assertSame('attachment; filename="' . $this->buildId . '.tar.gz"', $headers['Content-Disposition']);
	}
}
