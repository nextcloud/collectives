<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\CollectiveService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class CollectiveController extends Controller {
	/** @var CollectiveService */
	private $service;

	/** @var IUserSession */
	private $userSession;

	/** @var LoggerInterface */
	private $logger;

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
		return $this->handleErrorResponse(function () {
			return $this->service->getCollectives($this->getUserId());
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $name
	 *
	 * @return DataResponse
	 */
	public function create(string $name): DataResponse {
		return $this->handleErrorResponse(function () use ($name) {
			return $this->service->createCollective($this->getUserId(), $name);
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id) {
			return $this->service->deleteCollective($this->getUserId(), $id);
		}, $this->logger);
	}
}
