<?php

namespace OCA\Wiki\Controller;

use OCA\Wiki\Service\WikiCircleService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class WikiController extends Controller {
	/** @var WikiCircleService */
	private $service;
	/** @var IUserSession */
	private $userSession;

	use ErrorHelper;

	public function __construct(string $AppName,
								IRequest $request,
								WikiCircleService $service,
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
			return $this->service->getWikis($this->getUserId());
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
			return $this->service->createWiki($this->getUserId(), $name);
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
			return $this->service->deleteWiki($this->getUserId(), $id);
		});
	}
}
