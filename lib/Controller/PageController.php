<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SearchService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	use ErrorHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private PageService $service,
		private AttachmentService $attachmentService,
		private IUserSession $userSession,
		private SearchService $indexedSearchService,
		private CollectiveService $collectiveService,
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
		$pageInfos = $this->handleErrorResponse(function () use ($collectiveId): array {
			$userId = $this->getUserId();
			return $this->service->findAll($collectiveId, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfos]);
	}

	#[NoAdminRequired]
	public function get(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id): PageInfo {
			$userId = $this->getUserId();
			return $this->service->find($collectiveId, $id, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function create(int $collectiveId, int $parentId, string $title, ?int $templateId = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $parentId, $title, $templateId): PageInfo {
			$userId = $this->getUserId();
			return $this->service->create($collectiveId, $parentId, $title, $templateId, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function touch(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id): PageInfo {
			$userId = $this->getUserId();
			return $this->service->touch($collectiveId, $id, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function moveOrCopy(int $collectiveId, int $id, ?int $parentId = null, ?string $title = null, ?int $index = 0, bool $copy = false): DataResponse {
		$index ??= 0;
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $parentId, $title, $index, $copy): PageInfo {
			$userId = $this->getUserId();
			$pageInfo = $copy
				? $this->service->copy($collectiveId, $id, $parentId, $title, $index, $userId)
				: $this->service->move($collectiveId, $id, $parentId, $title, $index, $userId);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function moveOrCopyToCollective(int $collectiveId, int $id, int $newCollectiveId, ?int $parentId = null, ?int $index = 0, bool $copy = false): DataResponse {
		$index ??= 0;
		$this->handleErrorResponse(function () use ($collectiveId, $id, $newCollectiveId, $parentId, $index, $copy): void {
			$userId = $this->getUserId();
			if ($copy) {
				$this->service->copyToCollective($collectiveId, $id, $newCollectiveId, $parentId, $index, $userId);
			} else {
				$this->service->moveToCollective($collectiveId, $id, $newCollectiveId, $parentId, $index, $userId);

			}
		}, $this->logger);
		return new DataResponse([]);
	}

	#[NoAdminRequired]
	public function setEmoji(int $collectiveId, int $id, ?string $emoji = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $emoji): PageInfo {
			$userId = $this->getUserId();
			return $this->service->setEmoji($collectiveId, $id, $emoji, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function setFullWidth(int $collectiveId, int $id, bool $fullWidth): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $fullWidth): PageInfo {
			$userId = $this->getUserId();
			return $this->service->setFullWidth($collectiveId, $id, $userId, $fullWidth);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function setSubpageOrder(int $collectiveId, int $id, ?string $subpageOrder = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $subpageOrder): PageInfo {
			$userId = $this->getUserId();
			return $this->service->setSubpageOrder($collectiveId, $id, $subpageOrder, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function trash(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id): PageInfo {
			$userId = $this->getUserId();
			return $this->service->trash($collectiveId, $id, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function getAttachments(int $collectiveId, int $id): DataResponse {
		$attachments = $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			return $this->attachmentService->getAttachments($collectiveId, $id, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $attachments]);
	}

	#[NoAdminRequired]
	public function getBacklinks(int $collectiveId, int $id): DataResponse {
		$backlinks = $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			return $this->service->getBacklinks($collectiveId, $id, $userId);
		}, $this->logger);
		return new DataResponse(['data' => $backlinks]);
	}

	#[NoAdminRequired]
	public function contentSearch(int $collectiveId, string $searchString): DataResponse {
		$pageInfos = $this->handleErrorResponse(function () use ($collectiveId, $searchString): array {
			$userId = $this->getUserId();
			$collective = $this->collectiveService->getCollective($collectiveId, $userId);
			$results = $this->indexedSearchService->searchCollective($collective, $searchString, 100);
			$pages = [];
			foreach ($results as $value) {
				$pages[] = $this->service->find($collectiveId, $value['id'], $userId);
			}
			return $pages;
		}, $this->logger);
		return new DataResponse(['data' => $pageInfos]);
	}
}
