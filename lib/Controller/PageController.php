<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\AttachmentService;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	private PageService $service;
	private AttachmentService $attachmentService;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	use ErrorHelper;

	public function __construct(string            $appName,
		IRequest          $request,
		PageService       $service,
		AttachmentService $attachmentService,
		IUserSession      $userSession,
		LoggerInterface   $logger) {
		parent::__construct($appName, $request);
		$this->service = $service;
		$this->attachmentService = $attachmentService;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * @return string
	 */
	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 *
	 * @return DataResponse
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
	 *
	 * @param int $collectiveId
	 * @param int $id
	 *
	 * @return DataResponse
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
	 *
	 * @param int    $collectiveId
	 * @param int    $parentId
	 * @param string $title
	 *
	 * @return DataResponse
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
	 *
	 * @param int $collectiveId
	 * @param int $id
	 *
	 * @return DataResponse
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
	 *
	 * @param int         $collectiveId
	 * @param int         $id
	 * @param int|null    $parentId
	 * @param string|null $title
	 * @param int|null    $index
	 *
	 * @return DataResponse
	 */
	public function move(int $collectiveId, int $id, ?int $parentId = null, ?string $title = null, ?int $index = 0): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $parentId, $title, $index): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->move($collectiveId, $id, $parentId, $title, $index, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int      $collectiveId
	 * @param int      $id
	 * @param int      $newCollectiveId
	 * @param int|null $parentId
	 * @param int|null $index
	 *
	 * @return DataResponse
	 */
	public function moveToCollective(int $collectiveId, int $id, int $newCollectiveId, ?int $parentId = null, ?int $index = 0): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $newCollectiveId, $parentId, $index): array {
			$userId = $this->getUserId();
			$this->service->moveToCollective($collectiveId, $id, $newCollectiveId, $parentId, $index, $userId);
			return [];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int         $collectiveId
	 * @param int         $id
	 * @param string|null $emoji
	 *
	 * @return DataResponse
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
	 *
	 * @param int         $collectiveId
	 * @param int         $id
	 * @param string|null $subpageOrder
	 *
	 * @return DataResponse
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
	 *
	 * @param int $collectiveId
	 * @param int $id
	 *
	 * @return DataResponse
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
	 *
	 * @param int $collectiveId
	 * @param int $id
	 *
	 * @return DataResponse
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
	 *
	 * @param int $collectiveId
	 * @param int $id
	 *
	 * @return DataResponse
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
