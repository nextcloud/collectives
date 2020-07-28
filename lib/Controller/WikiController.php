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
		return new DataResponse($this->service->getWikis());
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $name
	 *
	 * @return DataResponse
	 */
	public function create(string $name): DataResponse {
		return new DataResponse($this->service->createWiki($name, $this->userId));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function destroy(int $id): DataResponse {
		return new DataResponse($this->service->deleteWiki($id, $this->userId));
	}
}
