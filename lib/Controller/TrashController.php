<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class TrashController extends Controller {
	use ErrorHelper;

	public function __construct(
		string $AppName,
		IRequest $request,
		private CollectiveService $service,
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

	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->handleErrorResponse(function (): array {
			$collectives = $this->service->getCollectivesTrash($this->getUserId());
			return [
				'data' => $collectives,
			];
		}, $this->logger);
	}

	#[NoAdminRequired]
	public function delete(int $id, bool $circle = false): DataResponse {
		return $this->handleErrorResponse(function () use ($circle, $id): array {
			$collective = $this->service->deleteCollective($id, $this->getUserId(), $circle);
			return [
				'data' => $collective,
			];
		}, $this->logger);
	}

	#[NoAdminRequired]
	public function restore(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$collective = $this->service->restoreCollective($id, $this->getUserId());
			return [
				'data' => $collective,
			];
		}, $this->logger);
	}
}
