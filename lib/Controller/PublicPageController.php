<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Model\CollectiveShareInfo;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\PublicShareController;
use OCP\IRequest;
use OCP\ISession;
use Psr\Log\LoggerInterface;

class PublicPageController extends PublicShareController {
	private CollectiveShareMapper $collectiveShareMapper;
	private CollectiveShareService $collectiveShareService;
	private PageService $service;
	private AttachmentService $attachmentService;
	private LoggerInterface $logger;
	private ?CollectiveShareInfo $share = null;

	use ErrorHelper;

	public function __construct(string                 $appName,
		IRequest               $request,
		CollectiveShareMapper  $collectiveShareMapper,
		CollectiveShareService $collectiveShareService,
		PageService            $service,
		AttachmentService      $attachmentService,
		ISession               $session,
		LoggerInterface        $logger) {
		parent::__construct($appName, $request, $session);
		$this->collectiveShareMapper = $collectiveShareMapper;
		$this->collectiveShareService = $collectiveShareService;
		$this->service = $service;
		$this->attachmentService = $attachmentService;
		$this->logger = $logger;
	}

	/**
	 * @return string
	 */
	protected function getPasswordHash(): string {
		return '';
	}

	/**
	 * @return bool
	 */
	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function isPasswordProtected(): bool {
		return false;
	}

	/**
	 * @return CollectiveShareInfo
	 * @throws NotFoundException
	 */
	private function getShare(): CollectiveShareInfo {
		if (null === $this->share) {
			$this->share = $this->collectiveShareService->findShareByToken($this->getToken());

			if ($this->share === null) {
				throw new NotFoundException('Failed to get shared collective');
			}
		}

		return $this->share;
	}

	/**
	 * @return void
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function checkEditPermissions(): void {
		if (!$this->getShare()->getEditable()) {
			throw new NotPermittedException('Not permitted to edit shared collective');
		}
	}

	/**
	 * @param int    $collectiveId
	 * @param int    $pageId
	 * @param int    $id
	 * @param string $owner
	 *
	 * @return void
	 * @throws NotPermittedException
	 */
	private function checkPageShareAccess(int $collectiveId, int $pageId, int $id, string $owner): void {
		try {
			$this->service->isPageInPageFolder($collectiveId, $pageId, $id, $owner);
		} catch (NotFoundException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param int      $collectiveId
	 * @param int      $pageId
	 * @param string   $owner
	 * @param PageInfo $pageInfo
	 *
	 * @return void
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function decoratePageInfo(int $collectiveId, int $pageId, string $owner, PageInfo $pageInfo): void {
		// Shares don't have a collective path
		$pageInfo->setCollectivePath('');
		// Remove root page from file path on page shares
		if ($pageId !== 0) {
			$rootPageName = $this->service->find($collectiveId, $pageId, $owner)->getTitle();
			$pageInfo->setFilePath(preg_replace('/^' . $rootPageName . '\/?/', '', $pageInfo->getFilePath()));
		}
		$pageInfo->setShareToken($this->getToken());
	}

	/**
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function (): array {
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$pageId = $this->getShare()->getPageId();
			if ($pageId === 0) {
				$pageInfos = $this->service->findAll($collectiveId, $owner);
			} else {
				$pageInfos = $this->service->findChildren($collectiveId, $pageId, $owner);
			}
			foreach ($pageInfos as $pageInfo) {
				$this->decoratePageInfo($collectiveId, $pageId, $owner, $pageInfo);
			}
			return [
				"data" => $pageInfos
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int    $id
	 *
	 * @return DataResponse
	 */
	public function get(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			if (0 !== $pageId = $this->getShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $pageId, $id, $owner);
			}
			$pageInfo = $this->service->find($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, $pageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int    $parentId
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function create(int $parentId, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $title): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			if (0 !== $pageId = $this->getShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $pageId, $parentId, $owner);
			}
			$pageInfo = $this->service->create($collectiveId, $parentId, $title, $owner);
			$this->decoratePageInfo($collectiveId, $pageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function touch(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			if (0 !== $pageId = $this->getShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $pageId, $id, $owner);
			}
			$pageInfo = $this->service->touch($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, $pageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int         $id
	 * @param int|null    $parentId
	 * @param string|null $title
	 * @param int|null    $index
	 * @param bool        $copy
	 *
	 * @return DataResponse
	 */
	public function moveOrCopy(int $id, ?int $parentId, ?string $title = null, ?int $index = 0, bool $copy = false): DataResponse {
		return $this->handleErrorResponse(function () use ($id, $parentId, $title, $index, $copy): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			if (0 !== $pageId = $this->getShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $pageId, $id, $owner);
				if ($parentId) {
					$this->checkPageShareAccess($collectiveId, $pageId, $parentId, $owner);
				}
			}
			$pageInfo = $copy
				? $this->service->copy($collectiveId, $id, $parentId, $title, $index, $owner)
				: $this->service->move($collectiveId, $id, $parentId, $title, $index, $owner);
			$this->decoratePageInfo($collectiveId, $pageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int         $id
	 * @param string|null $emoji
	 *
	 * @return DataResponse
	 */
	public function setEmoji(int $id, ?string $emoji = null): DataResponse {
		return $this->handleErrorResponse(function () use ($id, $emoji): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			if (0 !== $pageId = $this->getShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $pageId, $id, $owner);
			}
			$pageInfo = $this->service->setEmoji($collectiveId, $id, $emoji, $owner);
			$this->decoratePageInfo($collectiveId, $pageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int         $id
	 * @param string|null $subpageOrder
	 *
	 * @return DataResponse
	 */
	public function setSubpageOrder(int $id, ?string $subpageOrder = null): DataResponse {
		return $this->handleErrorResponse(function () use ($id, $subpageOrder): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			if (0 !== $pageId = $this->getShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $pageId, $id, $owner);
			}
			$pageInfo = $this->service->setSubpageOrder($collectiveId, $id, $subpageOrder, $owner);
			$this->decoratePageInfo($collectiveId, $pageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function trash(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$this->checkEditPermissions();
			if ($this->getShare()->getPageId()) {
				throw new NotPermittedException('Not permitted to trash page from page share');
			}
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$pageInfo = $this->service->trash($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, 0, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function getAttachments(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			if (0 !== $pageId = $this->getShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $pageId, $id, $owner);
			}
			$attachments = $this->attachmentService->getAttachments($collectiveId, $id, $owner);
			return [
				"data" => $attachments
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int    $id
	 *
	 * @return DataResponse
	 */
	public function getBacklinks(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			if (0 !== $pageId = $this->getShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $pageId, $id, $owner);
			}
			$backlinks = $this->service->getBacklinks($collectiveId, $id, $owner);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
