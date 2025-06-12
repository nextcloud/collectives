<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\TemplateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class TemplateController extends Controller {
	use ErrorHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private TemplateService $templateService,
		private LoggerInterface $logger,
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

	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$templateInfos = $this->handleErrorResponse(function () use ($collectiveId): array {
			$userId = $this->getUserId();
			return $this->templateService->getTemplates($collectiveId, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $templateInfos]);
	}

	#[NoAdminRequired]
	public function create(int $collectiveId, string $title, int $parentId): DataResponse {
		$templateInfo = $this->handleErrorResponse(function () use ($parentId, $collectiveId, $title): PageInfo {
			$userId = $this->getUserId();
			return $this->templateService->create($collectiveId, $parentId, $title, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $templateInfo]);
	}

	#[NoAdminRequired]
	public function delete(int $collectiveId, int $id): DataResponse {
		$this->handleErrorResponse(function () use ($collectiveId, $id): void {
			$userId = $this->getUserId();
			$this->templateService->delete($collectiveId, $id, $userId);
		}, $this->logger);
		return new DataResponse([]);
	}

	#[NoAdminRequired]
	public function rename(int $collectiveId, int $id, string $title): DataResponse {
		$templateInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $title): PageInfo {
			$userId = $this->getUserId();
			return $this->templateService->rename($collectiveId, $id, $title, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $templateInfo]);
	}

	#[NoAdminRequired]
	public function setEmoji(int $collectiveId, int $id, ?string $emoji = null): DataResponse {
		$templateInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $emoji): PageInfo {
			$userId = $this->getUserId();
			return $this->templateService->setEmoji($collectiveId, $id, $emoji, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $templateInfo]);
	}
}
