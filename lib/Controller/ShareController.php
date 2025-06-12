<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\CollectiveShareService;
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
 * Provides access to collective and page shares.
 *
 * @psalm-import-type CollectivesCollectiveShare from ResponseDefinitions
 */
class ShareController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $AppName,
		IRequest $request,
		private CollectiveService $collectiveService,
		private PageService $pageService,
		private CollectiveShareService $shareService,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * Get collective and page shares of a collective
	 *
	 * @param int $collectiveId ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, list<CollectivesCollectiveShare>, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Shares returned
	 */
	#[NoAdminRequired]
	public function getCollectiveShares(int $collectiveId): DataResponse {
		$share = $this->handleErrorResponse(function () use ($collectiveId): array {
			$userId = $this->userId;
			$collective = $this->collectiveService->getCollective($collectiveId, $userId);
			return $this->shareService->getSharesByCollectiveAndUser($userId, $collective->getId());
		}, $this->logger);
		return new DataResponse($share);
	}

	/**
	 * Create a collective share
	 *
	 * @param int $collectiveId ID of the collective
	 * @param string $password Optional password for the share
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesCollectiveShare, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Share created
	 */
	#[NoAdminRequired]
	public function createCollectiveShare(int $collectiveId, string $password = ''): DataResponse {
		return $this->createPageShare($collectiveId, 0, $password);
	}

	/**
	 * Update a collective share
	 *
	 * @param int $collectiveId ID of the collective
	 * @param string $token Token of the share
	 * @param bool $editable Whether share has edit permissions
	 * @param string $password Optional password for the share
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesCollectiveShare, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective, page or share not found
	 *
	 * 200: Share updated
	 */
	#[NoAdminRequired]
	public function updateCollectiveShare(int $collectiveId, string $token, bool $editable, string $password = ''): DataResponse {
		return $this->updatePageShare($collectiveId, 0, $token, $editable, $password);
	}

	/**
	 * Delete a collective share
	 *
	 * @param int $collectiveId ID of the collective
	 * @param string $token Token of the share
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective or share not found
	 *
	 * 200: Share deleted
	 */
	#[NoAdminRequired]
	public function deleteCollectiveShare(int $collectiveId, string $token): DataResponse {
		return $this->deletePageShare($collectiveId, 0, $token);
	}

	/**
	 * Create a page share
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $pageId ID of the page
	 * @param string $password Optional password for the share
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesCollectiveShare, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Share created
	 */
	#[NoAdminRequired]
	public function createPageShare(int $collectiveId, int $pageId = 0, string $password = ''): DataResponse {
		$share = $this->handleErrorResponse(function () use ($collectiveId, $pageId, $password): CollectiveShare {
			$collective = $this->collectiveService->getCollective($collectiveId, $this->userId);
			$pageInfo = null;
			if ($pageId !== 0) {
				$pageInfo = $this->pageService->pageToSubFolder($collectiveId, $pageId, $this->userId);
			}
			return $this->shareService->createShare($this->userId, $collective, $pageInfo, $password);
		}, $this->logger);
		return new DataResponse($share);
	}

	/**
	 * Update a page share
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $pageId ID of the page
	 * @param string $token Token of the share
	 * @param bool $editable Whether share has edit permissions
	 * @param ?string $password Optional password for the share
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesCollectiveShare, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective, page or share not found
	 *
	 * 200: Share updated
	 */
	#[NoAdminRequired]
	public function updatePageShare(int $collectiveId, int $pageId, string $token, bool $editable, ?string $password = null): DataResponse {
		$share = $this->handleErrorResponse(function () use ($collectiveId, $pageId, $token, $editable, $password): CollectiveShare {
			$userId = $this->userId;
			$collective = $this->collectiveService->getCollective($collectiveId, $userId);
			$pageInfo = null;
			if ($pageId !== 0) {
				$pageInfo = $this->pageService->findByFileId($collectiveId, $pageId, $userId);
			}
			return $this->shareService->updateShare($userId, $collective, $pageInfo, $token, $editable, $password);
		}, $this->logger);
		return new DataResponse($share);
	}

	/**
	 * Delete a page share
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $pageId ID of the page
	 * @param string $token Token of the share
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective, page or share not found
	 *
	 * 200: Share deleted
	 */
	#[NoAdminRequired]
	public function deletePageShare(int $collectiveId, int $pageId, string $token): DataResponse {
		$this->handleErrorResponse(function () use ($collectiveId, $pageId, $token): void {
			$userId = $this->userId;
			$this->collectiveService->getCollective($collectiveId, $userId);
			$this->shareService->deleteShare($userId, $collectiveId, $pageId, $token);
		}, $this->logger);
		return new DataResponse([]);
	}
}
