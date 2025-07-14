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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to pages of a collective.
 *
 * @psalm-import-type CollectivesPageInfo from ResponseDefinitions
 * @psalm-import-type CollectivesPageAttachment from ResponseDefinitions
 */
class PageController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private PageService $service,
		private IRootFolder $rootFolder,
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
	 * @return DataResponse<Http::STATUS_OK, array{pages: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective not found
	 *
	 * 200: Pages returned
	 */
	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$pageInfos = $this->handleErrorResponse(fn (): array => $this->service->findAll($collectiveId, $this->userId), $this->logger);
		return new DataResponse(['pages' => $pageInfos]);
	}

	/**
	 * Get one page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Page returned
	 */
	#[NoAdminRequired]
	public function get(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->find($collectiveId, $id, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Create a new page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $parentId ID of the parent page
	 * @param string $title Title of the page
	 * @param ?int $templateId ID of the template page to use
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or parent/template page not found
	 *
	 * 200: New page created
	 */
	#[NoAdminRequired]
	public function create(int $collectiveId, int $parentId, string $title, ?int $templateId = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->create($collectiveId, $parentId, $title, $templateId, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Touch a page (updates last edited user)
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or parent/template page not found
	 *
	 * 200: Page touched
	 */
	#[NoAdminRequired]
	public function touch(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->touch($collectiveId, $id, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Move or copy a page inside the collective
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 * @param ?int $parentId ID of target parent page (optional)
	 * @param ?string $title Target title (optional)
	 * @param ?int $index Index in subpage order (optional, default 0)
	 * @param bool $copy Copy the page instead of move (optional, default false)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or (parent) page not found
	 *
	 * 200: Page moved/copied
	 */
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

	/**
	 * Move or copy a page to another collective
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 * @param int $newCollectiveId ID of the target collective
	 * @param ?int $parentId ID of target parent page (optional)
	 * @param ?int $index Index in subpage order (optional, default 0)
	 * @param bool $copy Copy the page instead of move (optional, default false)
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or (parent) page not found
	 *
	 * 200: Page moved/copied
	 */
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

	/**
	 * Set/unset emoji for a page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 * @param ?string $emoji Emoji to set or null to unset (optional, default null)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Emoji set/unset
	 */
	#[NoAdminRequired]
	public function setEmoji(int $collectiveId, int $id, ?string $emoji = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->setEmoji($collectiveId, $id, $emoji, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Set/unset full width view for a page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 * @param bool $fullWidth Whether to enable full width view for the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Full width view set/unset
	 */
	#[NoAdminRequired]
	public function setFullWidth(int $collectiveId, int $id, bool $fullWidth): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->setFullWidth($collectiveId, $id, $this->userId, $fullWidth), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Set subpage order for a page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 * @param ?string $subpageOrder JSON-stringified array of subpage IDs
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Subpage order set
	 */
	#[NoAdminRequired]
	public function setSubpageOrder(int $collectiveId, int $id, ?string $subpageOrder = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->setSubpageOrder($collectiveId, $id, $subpageOrder, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Add tag to a page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 * @param int $tagId ID of the tag to add
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Tag added
	 */
	#[NoAdminRequired]
	public function addTag(int $collectiveId, int $id, int $tagId): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->addTag($collectiveId, $id, $tagId, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Remove tag from a page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 * @param int $tagId ID of the tag to remove
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Tag removed
	 */
	#[NoAdminRequired]
	public function removeTag(int $collectiveId, int $id, int $tagId): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->removeTag($collectiveId, $id, $tagId, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Trash a page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Page trashed
	 */
	#[NoAdminRequired]
	public function trash(int $collectiveId, int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(fn (): PageInfo => $this->service->trash($collectiveId, $id, $this->userId), $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Get attachments of a page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{attachments: list<CollectivesPageAttachment>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Attachments returned
	 */
	#[NoAdminRequired]
	public function getAttachments(int $collectiveId, int $id): DataResponse {
		$pageFile = $this->service->getPageFile($collectiveId, $id, $this->userId);
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$attachments = $this->handleErrorResponse(fn (): array => $this->attachmentService->getAttachments($pageFile, $userFolder), $this->logger);
		return new DataResponse(['attachments' => $attachments]);
	}

	/**
	 * Get backlinks of a page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{backlinks: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Backlinks returned
	 */
	#[NoAdminRequired]
	public function getBacklinks(int $collectiveId, int $id): DataResponse {
		$backlinks = $this->handleErrorResponse(fn (): array => $this->service->getBacklinks($collectiveId, $id, $this->userId), $this->logger);
		return new DataResponse(['backlinks' => $backlinks]);
	}

	/**
	 * Search the content of pages
	 *
	 * @param int $collectiveId ID of the collective
	 * @param string $searchString String to search for
	 *
	 * @return DataResponse<Http::STATUS_OK, array{pages: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective not found
	 *
	 * 200: Found pages returned
	 */
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
