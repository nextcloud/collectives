<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\CollectiveService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class TrashController extends Controller {
	private CollectiveService $service;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	use ErrorHelper;

	public function __construct(string $AppName,
		IRequest $request,
		CollectiveService $service,
		IUserSession $userSession,
		LoggerInterface $logger) {
		parent::__construct($AppName, $request);
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
	 * @return DataResponse
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function (): array {
			$collectives = $this->service->getCollectivesTrash($this->getUserId());
			return [
				"data" => $collectives,
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int  $id
	 * @param bool $circle
	 *
	 * @return DataResponse
	 */
	public function delete(int $id, bool $circle = false): DataResponse {
		return $this->handleErrorResponse(function () use ($circle, $id): array {
			$collective = $this->service->deleteCollective($id, $this->getUserId(), $circle);
			return [
				"data" => $collective,
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function restore(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$collective = $this->service->restoreCollective($id, $this->getUserId());
			return [
				"data" => $collective,
			];
		}, $this->logger);
	}
}
