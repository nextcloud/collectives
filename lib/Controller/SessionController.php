<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Session;
use OCA\Collectives\Service\SessionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to collectives user sessions.
 * Sessions are used to track active users/clients in a collective, e.g. to notify about updates.
 */
class SessionController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private SessionService $sessionService,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Start a session for a collective
	 *
	 * @param int $collectiveId ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{token: string}, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Session created, token returned
	 */
	#[NoAdminRequired]
	public function create(int $collectiveId): DataResponse {
		$session = $this->handleErrorResponse(fn (): Session => $this->sessionService->initSession($collectiveId, $this->userId), $this->logger);
		return new DataResponse(['token' => $session->getToken()]);
	}

	/**
	 * Update a session for a collective
	 *
	 * @param int $collectiveId ID of the collective
	 * @param string $token Token of the session
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSNotFoundException Session not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Session updated
	 */
	#[NoAdminRequired]
	public function sync(int $collectiveId, string $token): DataResponse {
		$this->handleErrorResponse(function () use ($collectiveId, $token): void {
			$this->sessionService->syncSession($collectiveId, $token, $this->userId);
		}, $this->logger);
		return new DataResponse([]);
	}

	/**
	 * Close a session for a collective
	 *
	 * @param int $collectiveId ID of the collective
	 * @param string $token Token of the session
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 *
	 * 200: Session closed or not found
	 */
	#[NoAdminRequired]
	public function close(int $collectiveId, string $token): DataResponse {
		$this->sessionService->closeSession($collectiveId, $token, $this->userId);
		return new DataResponse([]);
	}
}
