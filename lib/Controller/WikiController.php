<?php

namespace OCA\Wiki\Controller;

use OCA\Wiki\Service\WikiCircleService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class WikiController extends Controller {
	/** @var WikiCircleService */
	private $service;
	/** @var string */
	private $userId;

	use ErrorHelper;

	public function __construct(string $AppName,
								IRequest $request,
								WikiCircleService $service,
								string $userId
	) {
		parent::__construct($AppName, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function () {
			return $this->service->getWikis($this->userId);
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
			return $this->service->createWiki($this->userId, $name);
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
			return $this->service->deleteWiki($this->userId, $id);
		});
	}
}
