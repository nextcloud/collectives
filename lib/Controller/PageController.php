<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	/** @var PageService */
	private $service;

	/** @var IUserSession */
	private $userSession;

	/** @var LoggerInterface */
	private $logger;

	use ErrorHelper;

	public function __construct(string                $appName,
								IRequest              $request,
								PageService           $service,
								IUserSession          $userSession,
								LoggerInterface       $logger) {
		parent::__construct($appName, $request);
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
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function get(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->find($collectiveId, $parentId, $id, $userId);
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
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function touch(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->touch($collectiveId, $parentId, $id, $userId);
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
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $collectiveId, int $parentId, int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id, $title): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->rename($collectiveId, $parentId, $id, $title, $userId);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int         $collectiveId
	 * @param int         $parentId
	 * @param int         $id
	 * @param string|null $emoji
	 *
	 * @return DataResponse
	 */
	public function setEmoji(int $collectiveId, int $parentId, int $id, ?string $emoji = null): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id, $emoji): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->setEmoji($collectiveId, $parentId, $id, $emoji, $userId);
			return [
				"data" => $pageInfo
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
			$pageInfo = $this->service->delete($collectiveId, $parentId, $id, $userId);
			return [
				"data" => $pageInfo
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
			$backlinks = $this->service->getBacklinks($collectiveId, $parentId, $id, $userId);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
