<?php

namespace OCA\Wiki\Controller;

use OCA\Wiki\Service\PageService;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\AppFramework\Controller;

class PageController extends Controller {
	/** @var PageService */
	private $service;
	/** @var $string */
	private $userId;

	use Errors;

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
	 */
	public function index(): DataResponse {
		return new DataResponse($this->service->findAll($this->userId));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return Entity
	 */
	public function create(string $title, string $content): Entity {
		return $this->service->create($title, $content, $this->userId);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $id, string $title): DataResponse {
		return $this->handleNotFound(function() use ($id, $title) {
			return $this->service->rename($id, $title, $this->userId);
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
		return $this->handleNotFound(function() use ($id) {
			return $this->service->delete($id, $this->userId);
		});
	}
}
