<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\CollectiveService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class TrashController extends Controller {
	use ErrorHelper;

	public function __construct(string $AppName,
		IRequest $request,
		private CollectiveService $service,
		private IUserSession $userSession,
		private LoggerInterface $logger) {
		parent::__construct($AppName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	/**
	 * @NoAdminRequired
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
