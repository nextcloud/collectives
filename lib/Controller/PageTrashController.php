<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to page trash of a collective.
 *
 * @psalm-import-type CollectivesPageInfo from ResponseDefinitions
 */
class PageTrashController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private PageService $service,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get trashed pages of a collective
	 *
	 * @param int $collectiveId ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{pages: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective not found
	 *
	 * 200: Trashed pages returned
	 */
	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$pageInfos = $this->handleErrorResponse(fn (): array => $this->service->findAllTrash($collectiveId, $this->userId), $this->logger);
		return new DataResponse(['pages' => $pageInfos]);
	}

	/**
	 * Restore a page from trash
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the trashed page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Trashed page restored
	 */
	#[NoAdminRequired]
	public function restore(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->restore($collectiveId, $id, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Permanently delete a page from trash
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the trashed page
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Trashed page deleted
	 */
	#[NoAdminRequired]
	public function delete(int $collectiveId, int $id): DataResponse {
		$this->handleErrorResponse(function () use ($collectiveId, $id): void {
			$this->service->delete($collectiveId, $id, $this->userId);
		}, $this->logger);
		return new DataResponse([]);
	}
}
