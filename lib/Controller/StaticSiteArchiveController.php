<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\StaticSiteBuildNotFoundException;
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
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Download the archive of the current build of a static site.
	 *
	 * Not authenticated: the static site generator gets the URL with the unguessable static site
	 * and build IDs. Unknown static sites are throttled against guessing. Outdated builds of a
	 * known static site aren't, as the generator requests them when builds overlap.
	 *
	 * The status of the static site isn't checked, as the generator may download the archive
	 * before the response to its notification was processed.
	 *
	 * @throws DBException
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[BruteForceProtection(action: 'collectivesStaticSiteArchive')]
	public function download(string $staticSiteId, string $buildId): Response {
		try {
			$archive = $this->staticSiteService->getArchive($staticSiteId, $buildId);
		} catch (StaticSiteBuildNotFoundException) {
			return new Response(Http::STATUS_NOT_FOUND);
		} catch (NotFoundException) {
			$response = new Response(Http::STATUS_NOT_FOUND);
			$response->throttle();
			return $response;
		}

		$response = new FileDisplayResponse($archive);
		$response->addHeader('Content-Type', 'application/gzip');
		$response->addHeader('Content-Disposition', 'attachment; filename="' . $archive->getName() . '"');
		return $response;
	}
}
