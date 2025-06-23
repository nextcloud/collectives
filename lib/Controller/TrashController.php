<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\CollectiveService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Provides access to the collective trash.
 *
 * @psalm-import-type CollectivesCollective from ResponseDefinitions
 */
class TrashController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $AppName,
		IRequest $request,
		private CollectiveService $service,
		private IUserSession $userSession,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * Get trashed collectives
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collectives: list<CollectivesCollective>}, array{}>
	 * @throws OCSNotFoundException Something not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Trashed collectives returned
	 */
	#[NoAdminRequired]
	public function index(): DataResponse {
		$collectives = $this->handleErrorResponse(fn (): array => $this->service->getCollectivesTrash($this->userId), $this->logger);
		return new DataResponse(['collectives' => $collectives]);
	}

	/**
	 * Delete a collective from trash
	 *
	 * @param int $id ID of the collective
	 * @param bool $circle Whether to delete the team/circle as well (optional, default: false)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collective: CollectivesCollective}, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Trashed collective deleted
	 */
	#[NoAdminRequired]
	public function delete(int $id, bool $circle = false): DataResponse {
		$collective = $this->handleErrorResponse(fn (): Collective => $this->service->deleteCollective($id, $this->userId, $circle), $this->logger);
		return new DataResponse(['collective' => $collective]);
	}

	/**
	 * Restore a collective from trash
	 *
	 * @param int $id ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collective: CollectivesCollective}, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Trashed collective restored
	 */
	#[NoAdminRequired]
	public function restore(int $id): DataResponse {
		$collective = $this->handleErrorResponse(fn (): Collective => $this->service->restoreCollective($id, $this->userId), $this->logger);
		return new DataResponse(['collective' => $collective]);
	}
}
