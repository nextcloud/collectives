<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use Exception;
use JsonException;
use OCA\Collectives\ConfigLexicon;
use OCA\Collectives\Db\StaticSite;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for the external static site generator.
 */
class StaticSiteGeneratorClient {
	private const TIMEOUT = 10;

	public function __construct(
		private readonly IClientService $clientService,
		private readonly IAppConfig $appConfig,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @throws StaticSiteGeneratorNotConfiguredException
	 */
	public function assertConfigured(): void {
		if ($this->getBaseUrl() === '' || $this->getToken() === '') {
			throw new StaticSiteGeneratorNotConfiguredException($this->l10n->t('The static site generator is not configured'));
		}
	}

	/**
	 * @throws StaticSiteGeneratorException Not configured, not reachable or not healthy
	 */
	public function checkHealth(): void {
		$response = $this->request('GET', '/health');
		if ($response->getStatusCode() !== 200 || ($this->decodeBody($response)['status'] ?? null) !== 'ok') {
			throw new StaticSiteGeneratorException($this->l10n->t('The static site generator is not healthy (HTTP status %d)', [$response->getStatusCode()]));
		}
	}

	/**
	 * Ask the static site generator to build the static site from its archive.
	 *
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorUnavailableException Not reachable or temporarily unavailable
	 * @throws StaticSiteGeneratorException Request rejected
	 */
	public function build(StaticSite $staticSite, string $title): void {
		$staticSiteId = $staticSite->getStaticSiteId();
		$response = $this->request('POST', '/build', [
			'static_site_id' => $staticSiteId,
			'content_download_url' => $this->urlGenerator->linkToRouteAbsolute('collectives.static_site_archive.download', [
				'apiVersion' => '1.0',
				'staticSiteId' => $staticSiteId,
			]),
			'callback_status_url' => $this->urlGenerator->linkToRouteAbsolute('ocs.collectives.publicstaticsite.updatestatus', [
				'apiVersion' => '1.0',
				'staticSiteId' => $staticSiteId,
			]),
			'slug' => $staticSite->getSlug(),
			'title' => $title,
		]);

		$statusCode = $response->getStatusCode();
		$body = $this->decodeBody($response);
		if ($statusCode >= 200 && $statusCode < 300 && ($body['status'] ?? null) === 'enqueued') {
			return;
		}

		// The error of the generator may contain technical details, so it's only logged
		$this->logger->warning('Static site generator did not accept the build', [
			'staticSiteId' => $staticSiteId,
			'statusCode' => $statusCode,
			'error' => $body['error'] ?? null,
		]);
		$message = $this->l10n->t('The static site generator did not accept the build (HTTP status %d)', [$statusCode]);
		throw $statusCode >= 500
			? new StaticSiteGeneratorUnavailableException($message)
			: new StaticSiteGeneratorException($message);
	}

	/**
	 * Check the bearer token sent by the static site generator against the configured one.
	 */
	public function isValidToken(string $authorizationHeader): bool {
		$token = $this->getToken();
		return $token !== '' && hash_equals('Bearer ' . $token, $authorizationHeader);
	}

	/**
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorUnavailableException Not reachable
	 */
	private function request(string $method, string $path, ?array $json = null): IResponse {
		$this->assertConfigured();

		$options = [
			'headers' => ['Authorization' => 'Bearer ' . $this->getToken()],
			'timeout' => self::TIMEOUT,
			// Error status codes are handled by the caller
			'http_errors' => false,
		];
		if ($json !== null) {
			$options['json'] = $json;
		}

		try {
			return $this->clientService->newClient()->request($method, $this->getBaseUrl() . $path, $options);
		} catch (Exception $e) {
			$this->logger->warning('Failed to reach static site generator', ['exception' => $e]);
			throw new StaticSiteGeneratorUnavailableException($this->l10n->t('The static site generator is not reachable'), 0, $e);
		}
	}

	private function getBaseUrl(): string {
		return rtrim($this->appConfig->getValueString('collectives', ConfigLexicon::STATIC_SITE_GENERATOR_URL), '/');
	}

	private function getToken(): string {
		return $this->appConfig->getValueString('collectives', ConfigLexicon::STATIC_SITE_GENERATOR_TOKEN);
	}

	private function decodeBody(IResponse $response): array {
		try {
			$body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}
		return is_array($body) ? $body : [];
	}
}
