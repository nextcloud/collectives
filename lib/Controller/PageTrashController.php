<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageTrashController extends Controller {
	private PageService $service;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	use ErrorHelper;

	public function __construct(string            $appName,
		IRequest          $request,
		PageService       $service,
		IUserSession      $userSession,
		LoggerInterface   $logger) {
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
			$pageInfos = $this->service->findAllTrash($collectiveId, $userId);
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
	public function restore(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			$pageInfo = $this->service->restore($collectiveId, $id, $userId);
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
	public function delete(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id): array {
			$userId = $this->getUserId();
			$this->service->delete($collectiveId, $id, $userId);
			return [];
		}, $this->logger);
	}
}
