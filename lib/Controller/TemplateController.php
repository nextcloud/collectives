<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

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

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId): array {
			$userId = $this->getUserId();
			$templateInfos = $this->templateService->getTemplates($collectiveId, $userId);
			return [
				'data' => $templateInfos
			];
		}, $this->logger);
	}

	#[NoAdminRequired]
	public function create(int $collectiveId, string $title, int $parentId): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $collectiveId, $title): array {
			$userId = $this->getUserId();
			$templateInfo = $this->templateService->create($collectiveId, $parentId, $title, $userId);
			return [
				'data' => $templateInfo
			];
		}, $this->logger);
	}

	#[NoAdminRequired]
	public function delete(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			$this->templateService->delete($collectiveId, $id, $userId);
			return [];
		}, $this->logger);
	}

	#[NoAdminRequired]
	public function rename(int $collectiveId, int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $title): array {
			$userId = $this->getUserId();
			$pageInfo = $this->templateService->rename($collectiveId, $id, $title, $userId);
			return [
				'data' => $pageInfo
			];
		}, $this->logger);
	}

	#[NoAdminRequired]
	public function setEmoji(int $collectiveId, int $id, ?string $emoji = null): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $emoji): array {
			$userId = $this->getUserId();
			$templateInfo = $this->templateService->setEmoji($collectiveId, $id, $emoji, $userId);
			return [
				'data' => $templateInfo
			];
		}, $this->logger);
	}
}
