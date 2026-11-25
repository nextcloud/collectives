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
use PHPUnit\Framework\Constraint\Callback;
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
	private string $buildId = '01990000-0000-7000-8000-000000000001';

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
				['collectives.staticsitearchive.download', ['apiVersion' => '1.0', 'staticSiteId' => $this->staticSiteId, 'buildId' => $this->buildId], 'https://cloud.example.org/archive'],
			]);
		$urlGenerator->method('linkToOCSRouteAbsolute')
			->willReturnMap([
				['collectives.staticsitegenerator.updatestatus', ['apiVersion' => '1.0', 'staticSiteId' => $this->staticSiteId, 'buildId' => $this->buildId], 'https://cloud.example.org/callback'],
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
		$staticSite->setBuildId($this->buildId);
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

	private function expectedUpdatePayload(string $theme = 'wiki'): array {
		return [
			'data' => [
				'build_id' => $this->buildId,
				'content_download_url' => 'https://cloud.example.org/archive',
				'callback_status_url' => 'https://cloud.example.org/callback',
				'title' => 'My Site',
				'render_options' => ['theme' => $theme],
			],
		];
	}

	private function expectedCreatePayload(string $theme = 'wiki'): array {
		return ['data' => [
			'static_site_id' => $this->staticSiteId,
			'slug' => 'my-site',
		] + $this->expectedUpdatePayload($theme)['data']];
	}

	private function isRequestOptions(array $payload): Callback {
		return self::callback(static fn (array $options): bool
			=> $options['headers']['Authorization'] === 'Bearer secret-token'
			&& $options['timeout'] === 30
			&& $options['connect_timeout'] === 5
			&& $options['http_errors'] === false
			&& $options['json'] === $payload);
	}

	public function testCreatePostsStaticSiteWithBearerToken(): void {
		$this->httpClient->expects(self::once())
			->method('request')
			->with('POST', 'https://ssg.example.org/static-sites/', $this->isRequestOptions($this->expectedCreatePayload('topnav')))
			->willReturn($this->makeResponse(202, ''));

		$this->client->create($this->makeStaticSite(), 'My Site', 'topnav');
	}

	public function testCreateFallsBackToUpdateIfStaticSiteIsKnown(): void {
		$this->httpClient->expects(self::exactly(2))
			->method('request')
			->willReturnCallback(fn (string $method, string $url, array $options): IResponse => match ([$method, $url]) {
				['POST', 'https://ssg.example.org/static-sites/'] => $this->makeResponse(409, ''),
				['PATCH', 'https://ssg.example.org/static-sites/' . $this->staticSiteId] => $this->makeResponse(202, ''),
			});

		$this->client->create($this->makeStaticSite(), 'My Site', 'wiki');
	}

	public function testCreateDoesNotFallBackToUpdateIfSlugIsTaken(): void {
		$this->httpClient->expects(self::once())
			->method('request')
			->with('POST', 'https://ssg.example.org/static-sites/')
			->willReturn($this->makeResponse(409, '{"data": {"error_message": "slug already taken"}}'));
		$this->logger->expects(self::once())
			->method('warning')
			->with('Static site generator did not accept the request', self::callback(static fn (array $context): bool
				=> $context['statusCode'] === 409 && $context['error'] === 'slug already taken'));

		$this->expectException(StaticSiteGeneratorException::class);
		$this->client->create($this->makeStaticSite(), 'My Site', 'wiki');
	}

	/**
	 * The error is only read in the format of the spec, so a conflict in another format falls back to updating
	 */
	public function testCreateFallsBackToUpdateIfErrorHasAnotherFormat(): void {
		$this->httpClient->expects(self::exactly(2))
			->method('request')
			->willReturnOnConsecutiveCalls(
				$this->makeResponse(409, '{"status": "conflict", "error": "slug already taken"}'),
				$this->makeResponse(404, '{"status": "not_found", "error": "static site not found"}'),
			);
		$this->logger->expects(self::once())
			->method('warning')
			->with('Static site generator did not accept the request', self::callback(static fn (array $context): bool
				=> $context['statusCode'] === 404 && $context['error'] === null));

		$this->expectException(StaticSiteGeneratorException::class);
		$this->client->create($this->makeStaticSite(), 'My Site', 'wiki');
	}

	public function testCreateRejectedIfFallbackFails(): void {
		$this->httpClient->expects(self::exactly(2))
			->method('request')
			->willReturnOnConsecutiveCalls($this->makeResponse(409, ''), $this->makeResponse(404, ''));

		$this->expectException(StaticSiteGeneratorException::class);
		$this->client->create($this->makeStaticSite(), 'My Site', 'wiki');
	}

	public function testUpdatePatchesStaticSiteWithBearerToken(): void {
		$this->httpClient->expects(self::once())
			->method('request')
			->with('PATCH', 'https://ssg.example.org/static-sites/' . $this->staticSiteId, $this->isRequestOptions($this->expectedUpdatePayload('topnav')))
			->willReturn($this->makeResponse(202, ''));

		$this->client->update($this->makeStaticSite(), 'My Site', 'topnav');
	}

	public function testUpdateFallsBackToCreateIfStaticSiteIsUnknown(): void {
		$this->httpClient->expects(self::exactly(2))
			->method('request')
			->willReturnCallback(fn (string $method, string $url, array $options): IResponse => match ([$method, $url]) {
				['PATCH', 'https://ssg.example.org/static-sites/' . $this->staticSiteId] => $this->makeResponse(404, ''),
				['POST', 'https://ssg.example.org/static-sites/'] => $this->makeResponse(202, ''),
			});

		$this->client->update($this->makeStaticSite(), 'My Site', 'wiki');
	}

	public function testUpdateRejectedIfFallbackFails(): void {
		$this->httpClient->expects(self::exactly(2))
			->method('request')
			->willReturnOnConsecutiveCalls($this->makeResponse(404, ''), $this->makeResponse(409, ''));

		$this->expectException(StaticSiteGeneratorException::class);
		$this->client->update($this->makeStaticSite(), 'My Site', 'wiki');
	}

	public static function sendMethodProvider(): array {
		return [
			'create' => ['create'],
			'update' => ['update'],
		];
	}

	/**
	 * @dataProvider sendMethodProvider
	 */
	public function testSendWithSsgNotConfigured(string $method): void {
		$this->url = '';
		$this->httpClient->expects(self::never())->method('request');

		$this->expectException(StaticSiteGeneratorNotConfiguredException::class);
		$this->client->$method($this->makeStaticSite(), 'My Site', 'wiki');
	}

	/**
	 * @dataProvider sendMethodProvider
	 */
	public function testSendWithSsgNotReachable(string $method): void {
		$this->httpClient->method('request')->willThrowException(new Exception('Connection timed out'));

		$this->expectException(StaticSiteGeneratorUnavailableException::class);
		$this->client->$method($this->makeStaticSite(), 'My Site', 'wiki');
	}

	public static function unavailableResponseProvider(): array {
		return [
			'create, server error' => ['create', 503, '{"data": {"error_message": "queue full"}}'],
			'create, gateway error' => ['create', 502, ''],
			'update, server error' => ['update', 500, ''],
		];
	}

	/**
	 * @dataProvider unavailableResponseProvider
	 */
	public function testSendWithSsgUnavailable(string $method, int $statusCode, string $body): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse($statusCode, $body));

		$this->expectException(StaticSiteGeneratorUnavailableException::class);
		$this->client->$method($this->makeStaticSite(), 'My Site', 'wiki');
	}

	public static function rejectedResponseProvider(): array {
		return [
			'create, invalid' => ['create', 400, '{"data": {"error_message": "slug is taken"}}'],
			'create, unauthorized' => ['create', 401, ''],
			'create, not accepted' => ['create', 200, ''],
			'update, invalid' => ['update', 422, ''],
			'update, created' => ['update', 201, ''],
		];
	}

	/**
	 * @dataProvider rejectedResponseProvider
	 */
	public function testSendRejected(string $method, int $statusCode, string $body): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse($statusCode, $body));

		try {
			$this->client->$method($this->makeStaticSite(), 'My Site', 'wiki');
			$this->fail('Expected StaticSiteGeneratorException');
		} catch (StaticSiteGeneratorException $e) {
			$this->assertNotInstanceOf(StaticSiteGeneratorUnavailableException::class, $e);
		}
	}

	public function testSendLogsErrorOfGenerator(): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse(400, '{"data": {"error_message": "slug is taken"}}'));
		$this->logger->expects(self::once())
			->method('warning')
			->with('Static site generator did not accept the request', [
				'staticSiteId' => $this->staticSiteId,
				'buildId' => $this->buildId,
				'statusCode' => 400,
				'error' => 'slug is taken',
			]);

		$this->expectException(StaticSiteGeneratorException::class);
		$this->client->create($this->makeStaticSite(), 'My Site', 'wiki');
	}

	public function testDeleteSendsCallbackUrlWithBearerToken(): void {
		$this->httpClient->expects(self::once())
			->method('request')
			->with('DELETE', 'https://ssg.example.org/static-sites/' . $this->staticSiteId, self::callback(static fn (array $options): bool
				=> $options['headers']['Authorization'] === 'Bearer secret-token'
				&& $options['json'] === ['data' => ['callback_status_url' => 'https://cloud.example.org/callback']]))
			->willReturn($this->makeResponse(202, ''));

		$this->assertTrue($this->client->delete($this->makeStaticSite()));
	}

	public function testDeleteReturnsFalseForUnknownStaticSite(): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse(404, ''));

		$this->assertFalse($this->client->delete($this->makeStaticSite()));
	}

	public function testDeleteWithSsgUnavailable(): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse(503, ''));

		$this->expectException(StaticSiteGeneratorUnavailableException::class);
		$this->client->delete($this->makeStaticSite());
	}

	public function testDeleteRejected(): void {
		$this->httpClient->method('request')->willReturn($this->makeResponse(400, ''));

		try {
			$this->client->delete($this->makeStaticSite());
			$this->fail('Expected StaticSiteGeneratorException');
		} catch (StaticSiteGeneratorException $e) {
			$this->assertNotInstanceOf(StaticSiteGeneratorUnavailableException::class, $e);
		}
	}
}
