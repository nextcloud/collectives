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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class CollectiveUserSettingsController extends Controller {
	use ErrorHelper;

	public function __construct(
		string $AppName,
		IRequest $request,
		private CollectiveUserSettingsService $service,
		private IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($AppName, $request);
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
	public function pageOrder(int $id, int $pageOrder): DataResponse {
		return $this->prepareResponse(function () use ($id, $pageOrder): array {
			$this->service->setPageOrder(
				$id,
				$this->getUserId(),
				$pageOrder
			);
			return [];
		});
	}

	#[NoAdminRequired]
	public function showMembers(int $id, bool $showMembers): DataResponse {
		return $this->prepareResponse(function () use ($id, $showMembers): array {
			$this->service->setShowMembers(
				$id,
				$this->getUserId(),
				$showMembers
			);
			return [];
		});
	}

	#[NoAdminRequired]
	public function showRecentPages(int $id, bool $showRecentPages): DataResponse {
		return $this->prepareResponse(function () use ($id, $showRecentPages): array {
			$this->service->setShowRecentPages(
				$id,
				$this->getUserId(),
				$showRecentPages
			);
			return [];
		});
	}

	#[NoAdminRequired]
	public function favoritePages(int $id, string $favoritePages): DataResponse {
		return $this->prepareResponse(function () use ($id, $favoritePages): array {
			$this->service->setFavoritePages(
				$id,
				$this->getUserId(),
				$favoritePages
			);
			return [];
		});
	}
}
