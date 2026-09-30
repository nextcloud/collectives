<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides the external static site generator access to health checks and its build-status callback.
 *
 * @psalm-import-type CollectivesStaticSite from ResponseDefinitions
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
	 * Update the publication status of a static site.
	 *
	 * Called by the external static site generator as a build-progress callback. Authorized by the
	 * unguessable staticSiteId rather than a logged-in user or admin token.
	 *
	 * @param string $staticSiteId ID of the static site
	 * @param string $status New status ("published" or "failed")
	 * @param array{publish_url?: string, error_message?: string} $data Status-specific result payload
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesStaticSite, array{}>
	 * @throws OCSBadRequestException Invalid or missing status
	 * @throws OCSNotFoundException Static site not found
	 *
	 * 200: Static site status updated
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function updateStatus(string $staticSiteId, string $status = '', array $data = []): DataResponse {
		$staticSite = $this->handleErrorResponse(
			fn (): StaticSite => $this->staticSiteService->updateStatus($staticSiteId, $status, $data),
			$this->logger,
		);
		return new DataResponse($staticSite);
	}
}
