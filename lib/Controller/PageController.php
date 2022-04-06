<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\CollectiveServiceBase;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	/** @var CollectiveServiceBase */
	private $collectiveService;

	/** @var PageService */
	private $service;

	/** @var IUserSession */
	private $userSession;

	/** @var LoggerInterface */
	private $logger;

	use ErrorHelper;

	public function __construct(string                $appName,
								IRequest              $request,
								CollectiveServiceBase $collectiveService,
								PageService           $service,
								IUserSession          $userSession,
								LoggerInterface       $logger) {
		parent::__construct($appName, $request);
		$this->collectiveService = $collectiveService;
		$this->service = $service;
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
			$pages = $this->service->findAll($this->collectiveService->getCollective($collectiveId, $userId), $userId);
			return [
				"data" => $pages
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function get(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$userId = $this->getUserId();
			$page = $this->service->find($this->collectiveService->getCollective($collectiveId, $userId), $parentId, $id, $userId);
			return [
				"data" => $page
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
			$page = $this->service->create($this->collectiveService->getCollective($collectiveId, $userId), $parentId, $title, $userId);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function touch(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$userId = $this->getUserId();
			$page = $this->service->touch($this->collectiveService->getCollective($collectiveId, $userId), $parentId, $id, $userId);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param int    $parentId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $collectiveId, int $parentId, int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id, $title): array {
			$userId = $this->getUserId();
			$page = $this->service->rename($this->collectiveService->getCollective($collectiveId, $userId), $parentId, $id, $title, $userId);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function delete(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$userId = $this->getUserId();
			$page = $this->service->delete($this->collectiveService->getCollective($collectiveId, $userId), $parentId, $id, $userId);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function getBacklinks(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$userId = $this->getUserId();
			$backlinks = $this->service->getBacklinks($this->collectiveService->getCollective($collectiveId, $userId), $parentId, $id, $userId);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
