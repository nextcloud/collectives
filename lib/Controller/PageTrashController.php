<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageTrashController extends Controller {
	private IUserSession $userSession;

	use ErrorHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private PageService $service,
		IUserSession $userSession,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
		$this->userSession = $userSession;
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

	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$pageInfos = $this->handleErrorResponse(function () use ($collectiveId): array {
			$userId = $this->getUserId();
			return $this->service->findAllTrash($collectiveId, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfos]);
	}

	#[NoAdminRequired]
	public function restore(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id): PageInfo {
			$userId = $this->getUserId();
			return $this->service->restore($collectiveId, $id, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function delete(int $collectiveId, int $id): DataResponse {
		$this->handleErrorResponse(function () use ($collectiveId, $id): void {
			$userId = $this->getUserId();
			$this->service->delete($collectiveId, $id, $userId);
		}, $this->logger);
		return new DataResponse([]);
	}
}
