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
use OCP\AppFramework\Http;
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
	private const TIMEOUT = 30;
	// Fail fast if the static site generator is not reachable at all
	private const CONNECT_TIMEOUT = 5;
	private const STATIC_SITES_PATH = '/static-sites/';

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
	 * Ask the static site generator to create the static site from the archive of its current build.
	 *
	 * Falls back to updating it if the generator knows it already, e.g. if a previous request
	 * timed out but was accepted.
	 *
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorUnavailableException Not reachable or temporarily unavailable
	 * @throws StaticSiteGeneratorException Request rejected
	 */
	public function create(StaticSite $staticSite, string $title): void {
		$response = $this->sendCreate($staticSite, $title);
		if ($response->getStatusCode() === Http::STATUS_CONFLICT) {
			$response = $this->sendUpdate($staticSite, $title);
		}
		$this->assertAccepted($staticSite, $response);
	}

	/**
	 * Ask the static site generator to update the static site from the archive of its current build.
	 *
	 * Falls back to creating it if the generator doesn't know it, e.g. if it was removed there.
	 *
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorUnavailableException Not reachable or temporarily unavailable
	 * @throws StaticSiteGeneratorException Request rejected
	 */
	public function update(StaticSite $staticSite, string $title): void {
		$response = $this->sendUpdate($staticSite, $title);
		if ($response->getStatusCode() === Http::STATUS_NOT_FOUND) {
			$response = $this->sendCreate($staticSite, $title);
		}
		$this->assertAccepted($staticSite, $response);
	}

	/**
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorUnavailableException
	 */
	private function sendCreate(StaticSite $staticSite, string $title): IResponse {
		return $this->request('POST', self::STATIC_SITES_PATH, ['data' => $this->getPayload($staticSite, $title)]);
	}

	/**
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorUnavailableException
	 */
	private function sendUpdate(StaticSite $staticSite, string $title): IResponse {
		$path = self::STATIC_SITES_PATH . rawurlencode($staticSite->getStaticSiteId());
		return $this->request('PATCH', $path, ['data' => $this->getPayload($staticSite, $title)]);
	}

	private function getPayload(StaticSite $staticSite, string $title): array {
		$staticSiteId = $staticSite->getStaticSiteId();
		return [
			'static_site_id' => $staticSiteId,
			'build_id' => $staticSite->getBuildId(),
			'content_download_url' => $this->urlGenerator->linkToRouteAbsolute('collectives.staticsitearchive.download', [
				'apiVersion' => '1.0',
				'staticSiteId' => $staticSiteId,
				'buildId' => $staticSite->getBuildId(),
			]),
			'callback_status_url' => $this->urlGenerator->linkToOCSRouteAbsolute('collectives.staticsitegenerator.updatestatus', [
				'apiVersion' => '1.0',
				'staticSiteId' => $staticSiteId,
				'buildId' => $staticSite->getBuildId(),
			]),
			'slug' => $staticSite->getSlug(),
			'title' => $title,
		];
	}

	/**
	 * @throws StaticSiteGeneratorUnavailableException Temporarily unavailable
	 * @throws StaticSiteGeneratorException Request rejected
	 */
	private function assertAccepted(StaticSite $staticSite, IResponse $response): void {
		$statusCode = $response->getStatusCode();
		if ($statusCode === Http::STATUS_ACCEPTED) {
			return;
		}

		// The error of the generator may contain technical details, so it's only logged
		$this->logger->warning('Static site generator did not accept the build', [
			'staticSiteId' => $staticSite->getStaticSiteId(),
			'buildId' => $staticSite->getBuildId(),
			'statusCode' => $statusCode,
			'error' => $this->decodeBody($response)['error'] ?? null,
		]);
		$message = $this->l10n->t('The static site generator did not accept the build (HTTP status %d)', [$statusCode]);
		throw $statusCode >= 500
			? new StaticSiteGeneratorUnavailableException($message)
			: new StaticSiteGeneratorException($message);
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
			'connect_timeout' => self::CONNECT_TIMEOUT,
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
