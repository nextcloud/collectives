<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\ResponseDefinitions;
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
 * Allows the external static site builder service to report back export progress.
 *
 * @psalm-import-type CollectivesStaticSite from ResponseDefinitions
 */
class PublicStaticSiteController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private StaticSiteService $staticSiteService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Update status of a static site export
	 *
	 * @param string $staticSiteId ID of the static site
	 * @param string $status New status
	 * @param ?string $publishedUrl URL the static site was published to (optional)
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesStaticSite, array{}>
	 * @throws OCSBadRequestException Invalid status
	 * @throws OCSNotFoundException Static site not found
	 *
	 * 200: Static site status updated
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function updateStatus(string $staticSiteId, string $status, ?string $publishedUrl = null): DataResponse {
		$staticSite = $this->handleErrorResponse(
			fn (): StaticSite => $this->staticSiteService->updateStatus($staticSiteId, $status, $publishedUrl),
			$this->logger,
		);
		return new DataResponse($staticSite);
	}
}
