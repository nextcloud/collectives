<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Service\CollectiveUserSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class CollectiveUserSettingsController extends Controller {
	use ErrorHelper;

	public function __construct(string $AppName,
		IRequest $request,
		private CollectiveUserSettingsService $service,
		private IUserSession $userSession,
		private LoggerInterface $logger) {
		parent::__construct($AppName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function prepareResponse(Closure $callback) : DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function pageOrder(int $id, int $pageOrder): DataResponse {
		return $this->prepareResponse(function () use ($id, $pageOrder): array {
			$this->service->setPageOrder(
				$id,
				$this->getUserId(),
				$pageOrder
			);
			return [];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function showRecentPages(int $id, bool $showRecentPages): DataResponse {
		return $this->prepareResponse(function () use ($id, $showRecentPages): array {
			$this->service->setShowRecentPages(
				$id,
				$this->getUserId(),
				$showRecentPages
			);
			return [];
		});
	}
}
