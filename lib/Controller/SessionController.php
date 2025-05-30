<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use Closure;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\SessionService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SessionController extends OCSController {
	use ErrorHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private SessionService $sessionService,
		private LoggerInterface $logger,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @throws NotFoundException
	 */
	private function getUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new NotFoundException('Session user not found');
		}
		return $user->getUID();
	}

	private function prepareResponse(Closure $callback) : DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	#[NoAdminRequired]
	public function create(int $collectiveId): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId): array {
			$session = $this->sessionService->initSession($collectiveId, $this->getUserId());
			return ['token' => $session->getToken()];
		});
	}

	#[NoAdminRequired]
	public function sync(int $collectiveId, string $token): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId, $token): array {
			$this->sessionService->syncSession($collectiveId, $token, $this->getUserId());
			return [];
		});
	}

	#[NoAdminRequired]
	public function close(int $collectiveId, string $token): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId, $token): array {
			$this->sessionService->closeSession($collectiveId, $token, $this->getUserId());
			return [];
		});
	}
}
