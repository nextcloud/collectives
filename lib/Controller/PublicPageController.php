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
use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SearchService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\PublicShareController;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class PublicPageController extends PublicShareController {
	private ?IShare $share = null;
	private ?CollectiveShare $collectiveShare = null;

	use ErrorHelper;

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
	 * @throws ShareNotFound
	 */
	protected function getShare(): IShare {
		if ($this->share === null) {
			$this->share = $this->shareManager->getShareByToken($this->getToken());
		}
		return $this->share;
	}

	/**
	 * @throws NotFoundException
	 */
	private function getCollectiveShare(): CollectiveShare {
		if ($this->collectiveShare === null) {
			$this->collectiveShare = $this->collectiveShareService->findShareByToken($this->getToken());

			if ($this->collectiveShare === null) {
				throw new NotFoundException('Failed to get shared collective');
			}
		}

		return $this->collectiveShare;
	}

	/**
	 * @psalm-suppress InvalidNullableReturnType
	 * @psalm-suppress NullableReturnStatement
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

	protected function isPasswordProtected(): bool {
		return $this->getShare()->getPassword() !== null;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function checkEditPermissions(): void {
		if (!$this->getCollectiveShare()->getEditable()) {
			throw new NotPermittedException('Not permitted to edit shared collective');
		}
	}

	/**
	 * @throws NotPermittedException
	 */
	private function checkPageShareAccess(int $collectiveId, int $sharePageId, int $id, string $owner): void {
		try {
			$this->service->isPageInPageFolder($collectiveId, $sharePageId, $id, $owner);
		} catch (NotFoundException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
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
		return new DataResponse(['data' => $pageInfos]);
	}

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
		return new DataResponse(['data' => $pageInfo]);
	}

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
		return new DataResponse(['data' => $pageInfo]);
	}

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
		return new DataResponse(['data' => $pageInfo]);
	}

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
		return new DataResponse(['data' => $pageInfo]);
	}

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
		return new DataResponse(['data' => $pageInfo]);
	}

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
		return new DataResponse(['data' => $pageInfo]);
	}

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
		return new DataResponse(['data' => $pageInfo]);
	}

	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function getAttachments(int $id): DataResponse {
		$attachments = $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			return $this->attachmentService->getAttachments($collectiveId, $id, $owner);
		}, $this->logger);
		return new DataResponse(['data' => $attachments]);
	}

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
		return new DataResponse(['data' => $backlinks]);
	}

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
		return new DataResponse(['data' => $pages]);
	}
}
