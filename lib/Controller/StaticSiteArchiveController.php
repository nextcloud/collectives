<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\DB\Exception as DBException;
use OCP\IRequest;

/**
 * Provides static site archives to the external publish service.
 */
#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class StaticSiteArchiveController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly StaticSiteService $staticSiteService,
		private readonly StaticSiteGeneratorClient $staticSiteGeneratorClient,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Download the archive of a static site, authenticated by the bearer token of the external static site generator.
	 *
	 * The status of the static site isn't checked, as the generator may download the archive
	 * before the response to its notification was processed.
	 *
	 * @throws DBException
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'collectivesStaticSiteArchive')]
	public function download(string $staticSiteId): Response {
		// TODO(Publish): Re-enable once ssg-worker sends the bearer token (see BuildJob/ContentDownloader)
		// if (!$this->staticSiteGeneratorClient->isValidToken($this->request->getHeader('Authorization'))) {
		// 	$response = new Response(Http::STATUS_UNAUTHORIZED);
		// 	$response->throttle();
		// 	return $response;
		// }

		try {
			$archive = $this->staticSiteService->getArchive($staticSiteId);
		} catch (NotFoundException) {
			return new Response(Http::STATUS_NOT_FOUND);
		}

		$response = new FileDisplayResponse($archive);
		$response->addHeader('Content-Type', 'application/gzip');
		$response->addHeader('Content-Disposition', 'attachment; filename="' . $archive->getName() . '"');
		return $response;
	}
}
