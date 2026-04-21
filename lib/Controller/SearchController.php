<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\RecentPagesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Allows to search collectives.
 *
 * @psalm-import-type CollectivesPageInfo from ResponseDefinitions
 */
class SearchController extends OCSController {
	use OCSExceptionHelper;
	use UserTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RecentPagesService $recentPagesService,
		private readonly LoggerInterface $logger,
		private readonly ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get recent pages across collectives
	 *
	 * @param string|null $query Search term
	 * @param int $limit Limit of recent pages (default: 10, maximum 100)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{pages: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Not found
	 *
	 * 200: Recent pages returned
	 */
	#[NoAdminRequired]
	public function searchRecentPages(?string $query = null, int $limit = 10): DataResponse {
		$limit = min($limit, 100);
		$uid = $this->getUid();
		$pageInfos = $this->handleErrorResponse(function () use ($uid, $query, $limit): array {
			return $this->recentPagesService->forUserAsPageInfo($uid, $query, $limit);
		}, $this->logger);
		return new DataResponse(['pages' => $pageInfos]);
	}
}
