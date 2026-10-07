<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Controller;

use OCA\Collectives\Controller\StaticSiteController;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Service\ServiceException;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteGeneratorNotConfiguredException;
use OCA\Collectives\Service\StaticSiteGeneratorUnavailableException;
use OCA\Collectives\Service\StaticSiteService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class StaticSiteControllerTest extends TestCase {
	private StaticSiteService&MockObject $staticSiteService;
	private LoggerInterface&MockObject $logger;
	private StaticSiteController $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->staticSiteService = $this->createMock(StaticSiteService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->controller = new StaticSiteController(
			'collectives',
			$this->createMock(IRequest::class),
			$this->staticSiteService,
			$this->logger,
			$l10n,
			'jane',
		);
	}

	public function testCreateReturnsStaticSite(): void {
		$staticSite = new StaticSite();
		$this->staticSiteService->method('create')
			->with(42, [1], 'jane', 'Title', 'slug')
			->willReturn($staticSite);

		$this->assertSame($staticSite, $this->controller->create(42, [1], 'Title', 'slug')->getData());
	}

	public function testCreateLogsArchiveFailureWithContext(): void {
		$exception = new ServiceException('Failed to provide static site archive');
		$this->staticSiteService->method('create')->willThrowException($exception);
		$this->logger->expects($this->once())
			->method('error')
			->with('Failed to provide static site archive', ['collectiveId' => 42, 'exception' => $exception]);

		try {
			$this->controller->create(42, [1]);
			$this->fail('Expected OCSException');
		} catch (OCSException $e) {
			$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $e->getCode());
			$this->assertSame('The website could not be prepared. Please contact your administrator.', $e->getMessage());
		}
	}

	public function testUpdateLogsArchiveFailureWithContext(): void {
		$exception = new ServiceException('Failed to provide static site archive');
		$this->staticSiteService->method('update')->willThrowException($exception);
		$this->logger->expects($this->once())
			->method('error')
			->with('Failed to provide static site archive', ['collectiveId' => 42, 'id' => 7, 'exception' => $exception]);

		$this->expectException(OCSException::class);
		$this->controller->update(42, 7, [1], 'Title');
	}

	public static function generatorExceptionProvider(): array {
		return [
			'not configured' => [
				new StaticSiteGeneratorNotConfiguredException('Not configured'),
				Http::STATUS_SERVICE_UNAVAILABLE,
				'Publishing websites is not set up. Please contact your administrator.',
			],
			'unavailable' => [
				new StaticSiteGeneratorUnavailableException('Not reachable'),
				Http::STATUS_SERVICE_UNAVAILABLE,
				'The publish service is currently not available. Please try again later.',
			],
			'rejected' => [
				new StaticSiteGeneratorException('Rejected'),
				Http::STATUS_BAD_GATEWAY,
				'The publish service rejected the website. Please contact your administrator.',
			],
		];
	}

	/**
	 * @dataProvider generatorExceptionProvider
	 */
	public function testCreateTellsWhetherTryingAgainMayHelpIfGeneratorFails(StaticSiteGeneratorException $exception, int $statusCode, string $message): void {
		$this->staticSiteService->method('create')->willThrowException($exception);
		$this->logger->expects($this->never())->method('error');

		try {
			$this->controller->create(42, [1]);
			$this->fail('Expected OCSException');
		} catch (OCSException $e) {
			$this->assertSame($statusCode, $e->getCode());
			$this->assertSame($message, $e->getMessage());
		}
	}

	public function testCreateKeepsBadRequestForUnprocessableContent(): void {
		$this->staticSiteService->method('create')
			->willThrowException(new UnprocessableEntityException('There is no content to publish.'));
		$this->logger->expects($this->never())->method('error');

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('There is no content to publish.');
		$this->controller->create(42, [1]);
	}
}
