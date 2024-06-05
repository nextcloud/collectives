<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SearchService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	use ErrorHelper;

	public function __construct(string $appName,
		IRequest $request,
		private PageService $service,
		private AttachmentService $attachmentService,
		private IUserSession $userSession,
		private SearchService $indexedSearchService,
		private CollectiveService $collectiveService,
		private LoggerInterface $logger) {
		parent::__construct($appName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(int $collectiveId): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId): array {
			$userId = $this->getUserId();
			$pageInfos = $this->service->findAll($collectiveId, $userId);
			return [
				"data" => $pageInfos
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function get(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->find($collectiveId, $id, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(int $collectiveId, int $parentId, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $title): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->create($collectiveId, $parentId, $title, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function touch(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->touch($collectiveId, $id, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}


	/**
	 * @NoAdminRequired
	 */
	public function contentSearch(int $collectiveId, string $searchString): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $searchString): array {
			$userId = $this->getUserId();
			$collective = $this->collectiveService->getCollective($collectiveId, $userId);
			$results = $this->indexedSearchService->searchCollective($collective, $searchString, 100);
			$pages = [];
			foreach ($results as $value) {
				$pages[] = $this->service->find($collectiveId, $value['id'], $userId);
			}
			return [
				"data" => $pages
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function moveOrCopy(int $collectiveId, int $id, ?int $parentId = null, ?string $title = null, ?int $index = 0, bool $copy = false): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $parentId, $title, $index, $copy): array {
			$userId = $this->getUserId();
			$pageInfo = $copy
				? $this->service->copy($collectiveId, $id, $parentId, $title, $index, $userId)
				: $this->service->move($collectiveId, $id, $parentId, $title, $index, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function moveOrCopyToCollective(int $collectiveId, int $id, int $newCollectiveId, ?int $parentId = null, ?int $index = 0, bool $copy = false): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $newCollectiveId, $parentId, $index, $copy): array {
			$userId = $this->getUserId();
			if ($copy) {
				$this->service->copyToCollective($collectiveId, $id, $newCollectiveId, $parentId, $index, $userId);
			} else {
				$this->service->moveToCollective($collectiveId, $id, $newCollectiveId, $parentId, $index, $userId);

			}
			return [];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setEmoji(int $collectiveId, int $id, ?string $emoji = null): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $emoji): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->setEmoji($collectiveId, $id, $emoji, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function setSubpageOrder(int $collectiveId, int $id, ?string $subpageOrder = null): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $subpageOrder): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->setSubpageOrder($collectiveId, $id, $subpageOrder, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function trash(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->trash($collectiveId, $id, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getAttachments(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			$attachments = $this->attachmentService->getAttachments($collectiveId, $id, $userId);
			return [
				"data" => $attachments
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getBacklinks(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			$backlinks = $this->service->getBacklinks($collectiveId, $id, $userId);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
