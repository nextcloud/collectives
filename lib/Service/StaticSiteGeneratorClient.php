<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use Exception;
use GuzzleHttp\Exception\ConnectException;
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
 * HTTP client for the external static site generator. All requests are authorized with the API
 * token of the admin settings as bearer token.
 */
class StaticSiteGeneratorClient {
	private const TIMEOUT = 30;
	// Fail fast if the static site generator is not reachable at all
	private const CONNECT_TIMEOUT = 5;
	private const STATIC_SITES_PATH = '/static-sites';
	private const ERROR_SLUG_TAKEN = 'slug already taken';
	private const ERROR_OPERATION_IN_PROGRESS = 'operation in progress';

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
	 * If the generator answers with a conflict because it knows the static site already, e.g. as
	 * a previous request timed out but was accepted, an update is sent instead. A conflict because
	 * the slug is taken by another static site is a rejection.
	 *
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorTimeoutException Didn't answer after the request was sent
	 * @throws StaticSiteGeneratorUnavailableException Not reachable or temporarily unavailable
	 * @throws StaticSiteGeneratorBusyException Still processing a previous request for the static site
	 * @throws StaticSiteGeneratorException Request rejected
	 */
	public function create(StaticSite $staticSite, string $title, string $theme): void {
		$response = $this->sendCreate($staticSite, $title, $theme);
		if ($response->getStatusCode() === Http::STATUS_CONFLICT && $this->getErrorMessage($response) !== self::ERROR_SLUG_TAKEN) {
			$response = $this->sendUpdate($staticSite, $title, $theme);
		}
		$this->assertAccepted($staticSite, $response);
	}

	/**
	 * Ask the static site generator to update the static site from the archive of its current build.
	 *
	 * Falls back to creating it if the generator doesn't know it, e.g. if it was removed there.
	 *
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorTimeoutException Didn't answer after the request was sent
	 * @throws StaticSiteGeneratorUnavailableException Not reachable or temporarily unavailable
	 * @throws StaticSiteGeneratorBusyException Still processing a previous request for the static site
	 * @throws StaticSiteGeneratorException Request rejected
	 */
	public function update(StaticSite $staticSite, string $title, string $theme): void {
		$response = $this->sendUpdate($staticSite, $title, $theme);
		if ($response->getStatusCode() === Http::STATUS_NOT_FOUND) {
			$response = $this->sendCreate($staticSite, $title, $theme);
		}
		$this->assertAccepted($staticSite, $response);
	}

	/**
	 * Ask the static site generator to delete the static site. It reports the result to the
	 * callback URL of the current build.
	 *
	 * @return bool False if the generator doesn't know the static site
	 *
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorTimeoutException Didn't answer after the request was sent
	 * @throws StaticSiteGeneratorUnavailableException Not reachable or temporarily unavailable
	 * @throws StaticSiteGeneratorBusyException Still processing a previous request for the static site
	 * @throws StaticSiteGeneratorException Request rejected
	 */
	public function delete(StaticSite $staticSite): bool {
		$path = self::STATIC_SITES_PATH . '/' . rawurlencode($staticSite->getStaticSiteId());
		$response = $this->request('DELETE', $path, ['data' => ['callback_status_url' => $this->getCallbackUrl($staticSite)]]);
		if ($response->getStatusCode() === Http::STATUS_NOT_FOUND) {
			return false;
		}
		$this->assertAccepted($staticSite, $response);
		return true;
	}

	/**
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorUnavailableException
	 */
	private function sendCreate(StaticSite $staticSite, string $title, string $theme): IResponse {
		$payload = [
			'static_site_id' => $staticSite->getStaticSiteId(),
			'slug' => $staticSite->getSlug(),
		] + $this->getPayload($staticSite, $title, $theme);
		return $this->request('POST', self::STATIC_SITES_PATH, ['data' => $payload]);
	}

	/**
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorUnavailableException
	 */
	private function sendUpdate(StaticSite $staticSite, string $title, string $theme): IResponse {
		$path = self::STATIC_SITES_PATH . '/' . rawurlencode($staticSite->getStaticSiteId());
		return $this->request('PATCH', $path, ['data' => $this->getPayload($staticSite, $title, $theme)]);
	}

	private function getPayload(StaticSite $staticSite, string $title, string $theme): array {
		return [
			'build_id' => $staticSite->getBuildId(),
			'content_download_url' => $this->urlGenerator->linkToRouteAbsolute('collectives.staticsitearchive.download', [
				'apiVersion' => '1.0',
				'staticSiteId' => $staticSite->getStaticSiteId(),
				'buildId' => $staticSite->getBuildId(),
			]),
			'callback_status_url' => $this->getCallbackUrl($staticSite),
			'title' => $title,
			'render_options' => ['theme' => $theme],
		];
	}

	private function getCallbackUrl(StaticSite $staticSite): string {
		return $this->urlGenerator->linkToOCSRouteAbsolute('collectives.staticsitegenerator.updatestatus', [
			'apiVersion' => '1.0',
			'staticSiteId' => $staticSite->getStaticSiteId(),
			'buildId' => $staticSite->getBuildId(),
		]);
	}

	/**
	 * @throws StaticSiteGeneratorUnavailableException Temporarily unavailable
	 * @throws StaticSiteGeneratorBusyException Still processing a previous request for the static site
	 * @throws StaticSiteGeneratorException Request rejected
	 */
	private function assertAccepted(StaticSite $staticSite, IResponse $response): void {
		$statusCode = $response->getStatusCode();
		if ($statusCode === Http::STATUS_ACCEPTED) {
			return;
		}

		$error = $this->getErrorMessage($response);
		// The error of the generator may contain technical details, so it's only logged
		$this->logger->warning('Static site generator did not accept the request', [
			'staticSiteId' => $staticSite->getStaticSiteId(),
			'buildId' => $staticSite->getBuildId(),
			'statusCode' => $statusCode,
			'error' => $error,
		]);
		$message = $this->l10n->t('The static site generator did not accept the request (HTTP status %d)', [$statusCode]);
		if ($statusCode >= 500) {
			throw new StaticSiteGeneratorUnavailableException($message);
		}
		if ($statusCode === Http::STATUS_CONFLICT && $error === self::ERROR_OPERATION_IN_PROGRESS) {
			throw new StaticSiteGeneratorBusyException($message);
		}
		throw new StaticSiteGeneratorException($message);
	}

	/**
	 * @throws StaticSiteGeneratorNotConfiguredException
	 * @throws StaticSiteGeneratorTimeoutException Didn't answer after the request was sent
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
			if ($this->wasNotSent($e)) {
				throw new StaticSiteGeneratorUnavailableException($this->l10n->t('The static site generator is not reachable'), 0, $e);
			}
			throw new StaticSiteGeneratorTimeoutException($this->l10n->t('The static site generator did not answer'), 0, $e);
		}
	}

	/**
	 * Whether the request failed before it was sent, e.g. as the host couldn't be resolved or
	 * refused the connection. cURL reports no pretransfer time then.
	 */
	private function wasNotSent(Exception $e): bool {
		return $e instanceof ConnectException
			&& ($e->getHandlerContext()['pretransfer_time'] ?? 0) == 0;
	}

	private function getBaseUrl(): string {
		return rtrim($this->appConfig->getValueString('collectives', ConfigLexicon::STATIC_SITE_GENERATOR_URL), '/');
	}

	private function getToken(): string {
		return $this->appConfig->getValueString('collectives', ConfigLexicon::STATIC_SITE_GENERATOR_TOKEN);
	}

	private function getErrorMessage(IResponse $response): ?string {
		$errorMessage = $this->decodeBody($response)['data']['error_message'] ?? null;
		return is_string($errorMessage) ? $errorMessage : null;
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
