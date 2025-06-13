<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SearchService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to pages of a collective.
 *
 * @psalm-import-type CollectivesPageInfo from ResponseDefinitions
 */
class PageController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private PageService $service,
		private AttachmentService $attachmentService,
		private SearchService $indexedSearchService,
		private CollectiveService $collectiveService,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get pages of a collective
	 *
	 * @param int $collectiveId ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collectives: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective not found
	 *
	 * 200: Pages returned
	 */
	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$pageInfos = $this->handleErrorResponse(function () use ($collectiveId): array {
			return $this->service->findAll($collectiveId, $this->userId);
		}, $this->logger);
		return new DataResponse(['pages' => $pageInfos]);
	}

	#[NoAdminRequired]
	public function get(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id): PageInfo {
			return $this->service->find($collectiveId, $id, $this->userId);
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function create(int $collectiveId, int $parentId, string $title, ?int $templateId = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $parentId, $title, $templateId): PageInfo {
			return $this->service->create($collectiveId, $parentId, $title, $templateId, $this->userId);
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function touch(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id): PageInfo {
			return $this->service->touch($collectiveId, $id, $this->userId);
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function moveOrCopy(int $collectiveId, int $id, ?int $parentId = null, ?string $title = null, ?int $index = 0, bool $copy = false): DataResponse {
		$index ??= 0;
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $parentId, $title, $index, $copy): PageInfo {
			$pageInfo = $copy
				? $this->service->copy($collectiveId, $id, $parentId, $title, $index, $this->userId)
				: $this->service->move($collectiveId, $id, $parentId, $title, $index, $this->userId);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function moveOrCopyToCollective(int $collectiveId, int $id, int $newCollectiveId, ?int $parentId = null, ?int $index = 0, bool $copy = false): DataResponse {
		$index ??= 0;
		$this->handleErrorResponse(function () use ($collectiveId, $id, $newCollectiveId, $parentId, $index, $copy): void {
			if ($copy) {
				$this->service->copyToCollective($collectiveId, $id, $newCollectiveId, $parentId, $index, $this->userId);
			} else {
				$this->service->moveToCollective($collectiveId, $id, $newCollectiveId, $parentId, $index, $this->userId);

			}
		}, $this->logger);
		return new DataResponse([]);
	}

	#[NoAdminRequired]
	public function setEmoji(int $collectiveId, int $id, ?string $emoji = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $emoji): PageInfo {
			return $this->service->setEmoji($collectiveId, $id, $emoji, $this->userId);
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function setFullWidth(int $collectiveId, int $id, bool $fullWidth): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $fullWidth): PageInfo {
			return $this->service->setFullWidth($collectiveId, $id, $this->userId, $fullWidth);
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function setSubpageOrder(int $collectiveId, int $id, ?string $subpageOrder = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id, $subpageOrder): PageInfo {
			return $this->service->setSubpageOrder($collectiveId, $id, $subpageOrder, $this->userId);
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function trash(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($collectiveId, $id): PageInfo {
			return $this->service->trash($collectiveId, $id, $this->userId);
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	#[NoAdminRequired]
	public function getAttachments(int $collectiveId, int $id): DataResponse {
		$attachments = $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			return $this->attachmentService->getAttachments($collectiveId, $id, $this->userId);
		}, $this->logger);
		return new DataResponse(['attachments' => $attachments]);
	}

	#[NoAdminRequired]
	public function getBacklinks(int $collectiveId, int $id): DataResponse {
		$backlinks = $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			return $this->service->getBacklinks($collectiveId, $id, $this->userId);
		}, $this->logger);
		return new DataResponse(['backlinks' => $backlinks]);
	}

	#[NoAdminRequired]
	public function contentSearch(int $collectiveId, string $searchString): DataResponse {
		$pageInfos = $this->handleErrorResponse(function () use ($collectiveId, $searchString): array {
			$collective = $this->collectiveService->getCollective($collectiveId, $this->userId);
			$results = $this->indexedSearchService->searchCollective($collective, $searchString, 100);
			$pages = [];
			foreach ($results as $value) {
				$pages[] = $this->service->find($collectiveId, $value['id'], $this->userId);
			}
			return $pages;
		}, $this->logger);
		return new DataResponse(['pages' => $pageInfos]);
	}
}
