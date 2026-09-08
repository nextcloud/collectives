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
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to static site exports of a collective.
 *
 * @psalm-import-type CollectivesStaticSite from ResponseDefinitions
 */
class StaticSiteController extends OCSController {
	use OCSExceptionHelper;
	use UserTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private StaticSiteService $staticSiteService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get static sites of a collective
	 *
	 * @param int $collectiveId ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, list<CollectivesStaticSite>, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective not found
	 *
	 * 200: Static sites returned
	 */
	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$uid = $this->getUid();
		$staticSites = $this->handleErrorResponse(
			fn (): array => $this->staticSiteService->getStaticSites($collectiveId, $uid),
			$this->logger,
		);
		return new DataResponse($staticSites);
	}

	/**
	 * Start a static site export for a selection of pages of a collective
	 *
	 * @param int $collectiveId ID of the collective
	 * @param list<int> $pageIds IDs of the pages to publish
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesStaticSite, array{}>
	 * @throws OCSBadRequestException No pages selected
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Static site created
	 */
	#[NoAdminRequired]
	public function create(int $collectiveId, array $pageIds): DataResponse {
		$uid = $this->getUid();
		$staticSite = $this->handleErrorResponse(
			fn (): StaticSite => $this->staticSiteService->create($collectiveId, $pageIds, $uid),
			$this->logger,
		);
		return new DataResponse($staticSite);
	}
}
