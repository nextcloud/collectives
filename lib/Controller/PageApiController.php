<?php

namespace OCA\Wiki\Controller;

use OCA\Wiki\Service\PageService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class PageApiController extends ApiController {
	/** @var PageService */
	private $service;

	/** @var string */
	private $userId;

	use Errors;

	public function __construct(string $AppName,
                                IRequest $request,
                                PageService $service,
                                $userId) {
		parent::__construct($AppName, $request);
		$this->service = $service;
		$this->userId = $userId;
	}

	/**
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 */
	public function index(): DataResponse {
		return new DataResponse($this->service->findAll($this->userId));
	}

	/**
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function show(int $id): DataResponse {
		return $this->handleNotFound(function () use ($id) {
			return $this->service->find($id, $this->userId);
		});
	}

	/**
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return DataResponse
	 */
	public function create(string $title, string $content): DataResponse {
		return new DataResponse($this->service->create($title, $content,
			$this->userId));
	}

	/**
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param int    $id
	 * @param string $title
	 * @param string $content
	 *
	 * @return DataResponse
	 */
	public function update(int $id, string $title,
						   string $content): DataResponse {
		return $this->handleNotFound(function () use ($id, $title, $content) {
			return $this->service->update($id, $title, $content, $this->userId);
		});
	}

	/**
	 * @CORS
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function destroy(int $id): DataResponse {
		return $this->handleNotFound(function() use ($id) {
			return $this->service->delete($id, $this->userId);
		});
	}
}
