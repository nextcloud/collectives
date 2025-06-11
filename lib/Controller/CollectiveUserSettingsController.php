<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Service\CollectiveUserSettingsService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * Provides access to user settings for a specific collective.
 */
class CollectiveUserSettingsController extends OCSController {
	public function __construct(
		string $AppName,
		IRequest $request,
		private CollectiveUserSettingsService $service,
		private string $userId,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 */
	private function prepareResponse(Closure $callback): void {
		try {
			$callback();
		} catch (NotFoundException $e) {
			throw new OCSNotFoundException($e->getMessage());
		} catch (NotPermittedException $e) {
			throw new OCSForbiddenException($e->getMessage());
		}
	}

	/**
	 * Set order type for pages (in page list)
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $pageOrder Selected page order
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: showMembers settings was set
	 */
	#[NoAdminRequired]
	public function setPageOrder(int $collectiveId, int $pageOrder): DataResponse {
		$this->prepareResponse(function () use ($collectiveId, $pageOrder): void {
			$this->service->setPageOrder(
				$collectiveId,
				$this->userId,
				$pageOrder
			);
		});
		return new DataResponse([]);
	}

	/**
	 * Set whether members widget on landing page is expanded or not
	 *
	 * @param int $collectiveId ID of the collective
	 * @param bool $showMembers Whether members widget on landing page is expanded or not
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: showMembers setting was set
	 */
	#[NoAdminRequired]
	public function setShowMembers(int $collectiveId, bool $showMembers): DataResponse {
		$this->prepareResponse(function () use ($collectiveId, $showMembers): void {
			$this->service->setShowMembers(
				$collectiveId,
				$this->userId,
				$showMembers
			);
		});
		return new DataResponse([]);
	}

	/**
	 * Set whether recent pages widget on landing page is expanded or not
	 *
	 * @param int $collectiveId ID of the collective
	 * @param bool $showRecentPages Whether recent pages widget on landing page is expanded or not
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: showRecentPages setting was set
	 */
	#[NoAdminRequired]
	public function setShowRecentPages(int $collectiveId, bool $showRecentPages): DataResponse {
		$this->prepareResponse(function () use ($collectiveId, $showRecentPages): void {
			$this->service->setShowRecentPages(
				$collectiveId,
				$this->userId,
				$showRecentPages
			);
		});
		return new DataResponse([]);
	}

	/**
	 * Set list of page favorites
	 *
	 * @param int $collectiveId ID of the collective
	 * @param string $favoritePages JSON stringified array with favorite page IDs
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: page favorites were set
	 */
	#[NoAdminRequired]
	public function setFavoritePages(int $collectiveId, string $favoritePages): DataResponse {
		$this->prepareResponse(function () use ($collectiveId, $favoritePages): void {
			$this->service->setFavoritePages(
				$collectiveId,
				$this->userId,
				$favoritePages
			);
		});
		return new DataResponse([]);
	}
}
