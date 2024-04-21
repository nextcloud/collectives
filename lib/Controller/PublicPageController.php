<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
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
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class PublicPageController extends PublicShareController {
	private ?IShare $share = null;
	private ?CollectiveShare $collectiveShare = null;

	use ErrorHelper;

	public function __construct(string $appName,
		IRequest $request,
		private ShareManager $shareManager,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveShareService $collectiveShareService,
		private PageService $service,
		private AttachmentService $attachmentService,
		ISession $session,
		private LoggerInterface $logger) {
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

	protected function getPasswordHash(): string {
		return $this->getShare()->getPassword();
	}

	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException | MultipleObjectsReturnedException) {
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

	/**
	 * @PublicPage
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function (): array {
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
			return [
				"data" => $pageInfos
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function get(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->find($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function create(int $parentId, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $title): array {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $parentId, $owner);
			}
			$pageInfo = $this->service->create($collectiveId, $parentId, $title, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function touch(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->touch($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function moveOrCopy(int $id, ?int $parentId, ?string $title = null, ?int $index = 0, bool $copy = false): DataResponse {
		return $this->handleErrorResponse(function () use ($id, $parentId, $title, $index, $copy): array {
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
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function setEmoji(int $id, ?string $emoji = null): DataResponse {
		return $this->handleErrorResponse(function () use ($id, $emoji): array {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->setEmoji($collectiveId, $id, $emoji, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function setSubpageOrder(int $id, ?string $subpageOrder = null): DataResponse {
		return $this->handleErrorResponse(function () use ($id, $subpageOrder): array {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$pageInfo = $this->service->setSubpageOrder($collectiveId, $id, $subpageOrder, $owner);
			$this->decoratePageInfo($collectiveId, $sharePageId, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function trash(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$this->checkEditPermissions();
			if ($this->getCollectiveShare()->getPageId()) {
				throw new NotPermittedException('Not permitted to trash page from page share');
			}
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$pageInfo = $this->service->trash($collectiveId, $id, $owner);
			$this->decoratePageInfo($collectiveId, 0, $owner, $pageInfo);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function getAttachments(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$attachments = $this->attachmentService->getAttachments($collectiveId, $id, $owner);
			return [
				"data" => $attachments
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function getBacklinks(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			if (0 !== $sharePageId = $this->getCollectiveShare()->getPageId()) {
				$this->checkPageShareAccess($collectiveId, $sharePageId, $id, $owner);
			}
			$backlinks = $this->service->getBacklinks($collectiveId, $id, $owner);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
