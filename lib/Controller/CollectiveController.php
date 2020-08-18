<?php

namespace OCA\Unite\Controller;

use OCA\Unite\Service\CollectiveCircleService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class CollectiveController extends Controller {
	/** @var CollectiveCircleService */
	private $service;
	/** @var IUserSession */
	private $userSession;

	use ErrorHelper;

	public function __construct(string $AppName,
								IRequest $request,
								CollectiveCircleService $service,
								IUserSession $userSession) {
		parent::__construct($AppName, $request);
		$this->service = $service;
		$this->userSession = $userSession;
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
		});
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
		});
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
		});
	}
}
