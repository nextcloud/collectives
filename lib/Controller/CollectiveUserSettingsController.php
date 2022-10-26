<?php

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Service\CollectiveUserSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class CollectiveUserSettingsController extends Controller {
	private CollectiveUserSettingsService $service;
	private IUserSession $userSession;
	private LoggerInterface $logger;

	use ErrorHelper;

	public function __construct(string $AppName,
								IRequest $request,
								CollectiveUserSettingsService $service,
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
	 * @param Closure $callback
	 *
	 * @return DataResponse
	 */
	private function prepareResponse(Closure $callback) : DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param int $level
	 *
	 * @return DataResponse
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
}
