<?php

namespace OCA\Wiki\Controller;

use OCA\Wiki\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class PageController extends Controller {
	/** @var PageService */
	private $service;
	/** @var $string */
	private $userId;

	use ErrorHelper;

	public function __construct(string $appName,
								IRequest $request,
								PageService $service,
								string $userId) {
		parent::__construct($appName, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function () {
			return $this->service->findAll($this->userId);
		});
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function get(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id) {
			return $this->service->find($this->userId, $id);
		});
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function create(string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($title) {
			return $this->service->create($this->userId, $title);
		});
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($id, $title) {
			return $this->service->rename($this->userId, $id, $title);
		});
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id) {
			return $this->service->delete($this->userId, $id);
		});
	}
}
