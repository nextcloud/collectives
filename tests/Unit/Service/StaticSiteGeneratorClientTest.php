<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use Exception;
use OCA\Collectives\ConfigLexicon;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteGeneratorNotConfiguredException;
use OCA\Collectives\Service\StaticSiteGeneratorUnavailableException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class StaticSiteGeneratorClientTest extends TestCase {
	private IClient&MockObject $httpClient;
	private LoggerInterface&MockObject $logger;
	private StaticSiteGeneratorClient $client;

	private string $url = 'https://ssg.example.org/';
	private string $token = 'secret-token';
	private string $staticSiteId = '01990000-0000-7000-8000-000000000000';

	protected function setUp(): void {
		parent::setUp();

		$this->httpClient = $this->createMock(IClient::class);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($this->httpClient);
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')
			->willReturnCallback(fn (string $app, string $key): string => match ($key) {
				ConfigLexicon::STATIC_SITE_GENERATOR_URL => $this->url,
				ConfigLexicon::STATIC_SITE_GENERATOR_TOKEN => $this->token,
			});
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')
			->willReturnMap([
				['collectives.staticsitearchive.download', ['apiVersion' => '1.0', 'staticSiteId' => $this->staticSiteId], 'https://cloud.example.org/archive'],
				['ocs.collectives.staticsitegenerator.updatestatus', ['apiVersion' => '1.0', 'staticSiteId' => $this->staticSiteId], 'https://cloud.example.org/callback'],
			]);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => vsprintf($text, $parameters));
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->client = new StaticSiteGeneratorClient(
			$clientService,
			$appConfig,
			$urlGenerator,
			$l10n,
			$this->logger,
		);
	}

	private function makeStaticSite(): StaticSite {
		$staticSite = new StaticSite();
		$staticSite->setStaticSiteId($this->staticSiteId);
		$staticSite->setSlug('my-site');
		return $staticSite;
	}

	private function makeResponse(int $statusCode, string $body): IResponse&MockObject {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($statusCode);
		$response->method('getBody')->willReturn($body);
		return $response;
	}

	// TODO(Publish): Change tests when ext service provides a test endpoint to validate url and shared secret
	public function testCheckHealthSendsBearerToken(): void {
		$this->httpClient->expects(self::once())
			->method('request')
			->with('GET', 'https://ssg.example.org/health', self::callback(static fn (array $options): bool
				=> $options['headers']['Authorization'] === 'Bearer secret-token'
				&& $options['http_errors'] === false))
			->willReturn($this->makeResponse(200, '{"status": "ok"}'));

		$this->client->checkHealth();
	}

	public static function notConfiguredProvider(): array {
		return [
			'no URL' => ['', 'secret-token'],
			'no token' => ['https://ssg.example.org', ''],
		];
	}

	/**
	 * @dataProvider notConfiguredProvider
	 */
	public function testCheckHealthNotConfigured(string $url, string $token): void {
		$this->url = $url;
		$this->token = $token;
		$this->httpClient->expects(self::never())->method('request');

		$this->expectException(StaticSiteGeneratorException::class);
		$this->expectExceptionMessage('The static site generator is not configured');
		$this->client->checkHealth();
	}

	public function testCheckHealthNotReachable(): void {
		$this->httpClient->method('request')->willThrowException(new Exception('Connection refused'));

		$this->expectException(StaticSiteGeneratorException::class);
		$this->expectExceptionMessage('The static site generator is not reachable');
		$this->client->checkHealth();
	}

	public static function unhealthyResponseProvider(): array {
		return [
			'unauthorized' => [401, '{"status": "ok"}'],
			'server error' => [503, ''],
			'other status' => [200, '{"status": "degraded"}'],
			'no JSON' => [200, 'ok'],
		];
	}

	/**
	 * @dataProvider unhealthyResponseProvider
	 */
	public function testCheckHealthNotHealthy(int $statusCode, string $body): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse($statusCode, $body));

		$this->expectException(StaticSiteGeneratorException::class);
		$this->expectExceptionMessage('The static site generator is not healthy (HTTP status ' . $statusCode . ')');
		$this->client->checkHealth();
	}

	public function testBuildSendsStaticSiteWithBearerToken(): void {
		$this->httpClient->expects(self::once())
			->method('request')
			->with('POST', 'https://ssg.example.org/build', self::callback(fn (array $options): bool
				=> $options['headers']['Authorization'] === 'Bearer secret-token'
				&& $options['json'] === [
					'static_site_id' => $this->staticSiteId,
					'content_download_url' => 'https://cloud.example.org/archive',
					'callback_status_url' => 'https://cloud.example.org/callback',
					'slug' => 'my-site',
					'title' => 'My Site',
				]))
			->willReturn($this->makeResponse(202, '{"status": "enqueued"}'));

		$this->client->build($this->makeStaticSite(), 'My Site');
	}

	/**
	 * @dataProvider notConfiguredProvider
	 */
	public function testBuildWithSsgNotConfigured(string $url, string $token): void {
		$this->url = $url;
		$this->token = $token;
		$this->httpClient->expects(self::never())->method('request');

		$this->expectException(StaticSiteGeneratorNotConfiguredException::class);
		$this->client->build($this->makeStaticSite(), 'My Site');
	}

	public function testBuildWithSsgNotReachable(): void {
		$this->httpClient->method('request')->willThrowException(new Exception('Connection timed out'));

		$this->expectException(StaticSiteGeneratorUnavailableException::class);
		$this->client->build($this->makeStaticSite(), 'My Site');
	}

	public static function unavailableResponseProvider(): array {
		return [
			'server error' => [503, '{"status": "error", "error": "queue full"}'],
			'gateway error' => [502, ''],
		];
	}

	/**
	 * @dataProvider unavailableResponseProvider
	 */
	public function testBuildWithSsgUnavailable(int $statusCode, string $body): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse($statusCode, $body));

		$this->expectException(StaticSiteGeneratorUnavailableException::class);
		$this->client->build($this->makeStaticSite(), 'My Site');
	}

	public static function rejectedResponseProvider(): array {
		return [
			'invalid' => [400, '{"status": "invalid", "error": "slug is taken"}'],
			'unauthorized' => [401, ''],
			'not enqueued' => [200, '{"status": "ok"}'],
			'no JSON' => [202, 'enqueued'],
		];
	}

	/**
	 * @dataProvider rejectedResponseProvider
	 */
	public function testBuildRejected(int $statusCode, string $body): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse($statusCode, $body));

		try {
			$this->client->build($this->makeStaticSite(), 'My Site');
			$this->fail('Expected StaticSiteGeneratorException');
		} catch (StaticSiteGeneratorException $e) {
			$this->assertNotInstanceOf(StaticSiteGeneratorUnavailableException::class, $e);
		}
	}

	public function testBuildLogsErrorOfGenerator(): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse(400, '{"status": "invalid", "error": "slug is taken"}'));
		$this->logger->expects(self::once())
			->method('warning')
			->with('Static site generator did not accept the build', [
				'staticSiteId' => $this->staticSiteId,
				'statusCode' => 400,
				'error' => 'slug is taken',
			]);

		$this->expectException(StaticSiteGeneratorException::class);
		$this->client->build($this->makeStaticSite(), 'My Site');
	}

	public static function tokenProvider(): array {
		return [
			'matching token' => ['secret-token', 'Bearer secret-token', true],
			'wrong token' => ['secret-token', 'Bearer other', false],
			'missing scheme' => ['secret-token', 'secret-token', false],
			'missing header' => ['secret-token', '', false],
			'no token configured' => ['', 'Bearer ', false],
		];
	}

	/**
	 * @dataProvider tokenProvider
	 */
	public function testIsValidToken(string $configuredToken, string $authorizationHeader, bool $expected): void {
		$this->token = $configuredToken;

		$this->assertSame($expected, $this->client->isValidToken($authorizationHeader));
	}
}
