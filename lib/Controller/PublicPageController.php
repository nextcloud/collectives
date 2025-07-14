<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SearchService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Provides access to pages of a public collective/page share.
 *
 * @psalm-import-type CollectivesPageInfo from ResponseDefinitions
 * @psalm-import-type CollectivesPageAttachment from ResponseDefinitions
 */
class PublicPageController extends CollectivesPublicOCSController {
	use OCSExceptionHelper;

	private ?IShare $share = null;
	private ?CollectiveShare $collectiveShare = null;

	public function __construct(
		string $appName,
		IRequest $request,
		private ShareManager $shareManager,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveShareService $collectiveShareService,
		private PageService $service,
		private AttachmentService $attachmentService,
		private SearchService $indexedSearchService,
		private CollectiveService $collectiveService,
		ISession $session,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request, $session);
	}

	/**
	 * @throws OCSNotFoundException
	 */
	protected function getShare(): IShare {
		if ($this->share === null) {
			try {
				$this->share = $this->shareManager->getShareByToken($this->getToken());
			} catch (ShareNotFound $e) {
				throw new OCSNotFoundException($e->getMessage());
			}
		}
		return $this->share;
	}

	/**
	 * @throws OCSNotFoundException
	 */
	private function getCollectiveShare(): CollectiveShare {
		if ($this->collectiveShare === null) {
			$this->collectiveShare = $this->collectiveShareService->findShareByToken($this->getToken());

			if ($this->collectiveShare === null) {
				throw new OCSNotFoundException('Failed to get shared collective');
			}
		}

		return $this->collectiveShare;
	}

	/**
	 * @psalm-suppress InvalidNullableReturnType
	 * @psalm-suppress NullableReturnStatement
	 * @throws OCSNotFoundException
	 */
	protected function getPasswordHash(): string {
		return $this->getShare()->getPassword();
	}

	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return false;
		}

		return true;
	}

	/**
	 * @throws OCSNotFoundException
	 */
	protected function isPasswordProtected(): bool {
		return $this->getShare()->getPassword() !== null;
	}

	/**
	 * @throws OCSNotFoundException
	 * @throws OCSForbiddenException
	 */
	private function checkEditPermissions(): void {
		if (!$this->getCollectiveShare()->getEditable()) {
			throw new OCSForbiddenException('Not permitted to edit shared collective');
		}
	}

	/**
	 * @throws OCSForbiddenException
	 */
	private function checkPageShareAccess(int $collectiveId, int $sharePageId, int $id, string $owner): void {
		try {
			$this->service->isPageInPageFolder($collectiveId, $sharePageId, $id, $owner);
		} catch (NotFoundException|NotPermittedException $e) {
			throw new OCSForbiddenException($e->getMessage());
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function decoratePageInfo(int $collectiveId, int $sharePageId, string $owner, PageInfo $pageInfo): void {
		// Shares don't have a collective path
		$pageInfo->setCollectivePath('');
		// Remove root page from file path on page shares
		if ($sharePageId !== 0) {
			$rootPagePath = $this->service->find($collectiveId, $sharePageId, $owner)->getFilePath();
			$pageInfo->setFilePath(preg_replace('/^' . preg_quote($rootPagePath, '/') . '\/?/', '', $pageInfo->getFilePath()));
		}
		$pageInfo->setShareToken($this->getToken());
	}

	/**
	 * Get pages of a collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{pages: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Public collective/page share not found
	 *
	 * 200: Pages returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function index(): DataResponse {
		$pageInfos = $this->handleErrorResponse(function (): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$sharePageId = $this->getCollectiveShare()->getPageId();
			if ($sharePageId === 0) {
				$pageInfos = $this->service->findAll($collectiveId, $owner);
			} else {
				$pageInfos = $this->service->findChildren($collectiveId, $sharePageId, $owner);
			}
			foreach ($pageInfos as $pageInfo) {
				$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			}
			return $pageInfos;
		}, $this->logger);
		return new DataResponse(['pages' => $pageInfos]);
	}

	/**
	 * Get one page
	 *
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Page returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function get(int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($id): PageInfo {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->find($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Create a new page
	 *
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
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function create(int $parentId, string $title, ?int $templateId = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($parentId, $title, $templateId): PageInfo {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $parentId, $owner);
			}
			$pageInfo = $this->service->create($collectiveId, $parentId, $title, $templateId, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Touch a page (updates last edited user)
	 *
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or parent/template page not found
	 *
	 * 200: Page touched
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function touch(int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($id): PageInfo {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->touch($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Move or copy a page inside the collective
	 *
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
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function moveOrCopy(int $id, ?int $parentId, ?string $title = null, ?int $index = 0, bool $copy = false): DataResponse {
		$index ??= 0;
		$pageInfo = $this->handleErrorResponse(function () use ($id, $parentId, $title, $index, $copy): PageInfo {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
				if ($parentId) {
					$this->checkPageShareAccess($collectiveId, $sharePageId, $parentId, $owner);
				}
			}
			$pageInfo = $copy
				? $this->service->copy($collectiveId, $id, $parentId, $title, $index, $owner)
				: $this->service->move($collectiveId, $id, $parentId, $title, $index, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Set/unset full width view for a page
	 *
	 * @param int $id ID of the page
	 * @param bool $fullWidth Whether to enable full width view for the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Full width view set/unset
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function setFullWidth(int $id, bool $fullWidth): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($id, $fullWidth): PageInfo {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->setFullWidth($collectiveId, $id, $owner, $fullWidth);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Set/unset emoji for a page
	 *
	 * @param int $id ID of the page
	 * @param ?string $emoji Emoji to set or null to unset (optional, default null)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Emoji set/unset
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function setEmoji(int $id, ?string $emoji = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($id, $emoji): PageInfo {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->setEmoji($collectiveId, $id, $emoji, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Set subpage order for a page
	 *
	 * @param int $id ID of the page
	 * @param ?string $subpageOrder JSON-stringified array of subpage IDs
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Subpage order set
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function setSubpageOrder(int $id, ?string $subpageOrder = null): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($id, $subpageOrder): PageInfo {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->setSubpageOrder($collectiveId, $id, $subpageOrder, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Add tag to a page
	 *
	 * @param int $id ID of the page
	 * @param int $tagId ID of the tag to add
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Tag added
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function addTag(int $id, int $tagId): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($id, $tagId): PageInfo {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->addTag($collectiveId, $id, $tagId, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Remove tag from a page
	 *
	 * @param int $id ID of the page
	 * @param int $tagId ID of the tag to remove
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Tag removed
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function removeTag(int $id, int $tagId): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($id, $tagId): PageInfo {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->removeTag($collectiveId, $id, $tagId, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Trash a page
	 *
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{page: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Page trashed
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function trash(int $id): DataResponse {
		$pageInfo = $this->handleErrorResponse(function () use ($id): PageInfo {
			$this->checkEditPermissions();
			if ($this->getCollectiveShare()->getPageId()) {
				throw new NotPermittedException('Not permitted to trash page from page share');
			}
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$pageInfo = $this->service->trash($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, 0, $owner, $pageInfo);
			return $pageInfo;
		}, $this->logger);
		return new DataResponse(['page' => $pageInfo]);
	}

	/**
	 * Get attachments of a page
	 *
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{attachments: list<CollectivesPageAttachment>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Attachments returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function getAttachments(int $id): DataResponse {
		$attachments = $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageFile = $this->service->getPageFile($collectiveId, $id, $owner);
			$shareFolder = $this->getShare()->getNode();
			return $this->attachmentService->getAttachments($pageFile, $shareFolder);
		}, $this->logger);
		return new DataResponse(['attachments' => $attachments]);
	}

	/**
	 * Get backlinks of a page
	 *
	 * @param int $id ID of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{backlinks: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Backlinks returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function getBacklinks(int $id): DataResponse {
		$backlinks = $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			return $this->service->getBacklinks($collectiveId, $id, $owner);
		}, $this->logger);
		return new DataResponse(['backlinks' => $backlinks]);
	}

	/**
	 * Search the content of pages
	 *
	 * @param string $searchString String to search for
	 *
	 * @return DataResponse<Http::STATUS_OK, array{pages: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective not found
	 *
	 * 200: Found pages returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function contentSearch(string $searchString): DataResponse {
		$pages = $this->handleErrorResponse(function () use ($searchString): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$collective = $this->collectiveService->getCollective($collectiveId, $owner);
			$results = $this->indexedSearchService->searchCollective($collective, $searchString, 100);
			$pages = [];
			foreach ($results as $value) {
				if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
					try {
						$this->checkPageShareAccess($collectiveId, $sharePageId, $value['id'], $owner);
					} catch (NotPermittedException) {
						continue;
					}
				}
				$pages[] = $this->service->find($collectiveId, $value['id'], $owner);
			}
			return $pages;
		}, $this->logger);
		return new DataResponse(['pages' => $pages]);
	}
}
