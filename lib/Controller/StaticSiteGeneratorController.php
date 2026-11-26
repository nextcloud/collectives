<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\StaticSiteBuildNotFoundException;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides the external static site generator access to health checks and its build-status callback.
 */
class StaticSiteGeneratorController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly StaticSiteGeneratorClient $client,
		private readonly StaticSiteService $staticSiteService,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Check whether the configured static site generator is reachable and accepts the token
	 *
	 * This endpoint requires admin access
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status: 'ok'}, array{}>|DataResponse<Http::STATUS_BAD_GATEWAY, array{message: string}, array{}>
	 *
	 * 200: Static site generator is healthy
	 * 502: Static site generator is not configured, not reachable or not healthy
	 */
	// TODO(Publish): Change when ext service provides a test endpoint to validate url and shared secret
	public function health(): DataResponse {
		try {
			$this->client->checkHealth();
		} catch (StaticSiteGeneratorException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(['status' => 'ok']);
	}

	/**
	 * Report the result of a build or deletion of a static site.
	 *
	 * Called by the external static site generator once a build or deletion finished. Not authenticated, the
	 * callback URL with the unguessable static site and build IDs authorizes it. Unknown static
	 * sites are throttled against guessing, unknown builds of a known static site aren't.
	 *
	 * @param string $staticSiteId ID of the static site
	 * @param string $buildId ID of the build
	 * @param string $status Result of the build ("published" or "failed") or of the deletion ("deleted" or "failed")
	 * @param array{publish_url?: string, error_message?: string, build_id?: string} $data Status-specific result payload, `publish_url` is required for status "published": an http(s) URL of at most 255 characters, otherwise the publication is stored as failed. `build_id` is optional and must match the build of the URL
	 *
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 * @throws OCSBadRequestException Invalid or missing status
	 *
	 * 200: Result of the build or deletion processed
	 * 404: Static site or build not found
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 60, period: 60)]
	#[BruteForceProtection(action: 'collectivesStaticSiteStatus')]
	public function updateStatus(string $staticSiteId, string $buildId, string $status = '', array $data = []): DataResponse {
		try {
			$buildFound = $this->handleErrorResponse(function () use ($staticSiteId, $buildId, $status, $data): bool {
				try {
					$this->staticSiteService->updateStatus($staticSiteId, $buildId, $status, $data);
				} catch (StaticSiteBuildNotFoundException) {
					return false;
				}
				return true;
			}, $this->logger);
		} catch (OCSNotFoundException) {
			$response = new DataResponse([], Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}
		return $buildFound ? new DataResponse([]) : new DataResponse([], Http::STATUS_NOT_FOUND);
	}
}
