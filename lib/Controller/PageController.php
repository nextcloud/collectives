<?php

namespace OCA\Wiki\Controller;

use OCA\Wiki\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class PageController extends Controller {
	/** @var PageService */
	private $service;
	/** @var IUserSession */
	private $userSession;

	use ErrorHelper;

	public function __construct(string $appName,
								IRequest $request,
								PageService $service,
								IUserSession $userSession) {
		parent::__construct($appName, $request);
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
	 * @param int $wikiId
	 */
	public function index(int $wikiId): DataResponse {
		return $this->handleErrorResponse(function () use ($wikiId) {
			return $this->service->findAll($this->getUserId(), $wikiId);
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $wikiId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function get(int $wikiId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($wikiId, $id) {
			return $this->service->find($this->getUserId(), $wikiId, $id);
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $wikiId
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function create(int $wikiId, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($wikiId, $title) {
			return $this->service->create($this->getUserId(), $wikiId, $title);
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $wikiId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $wikiId, int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($wikiId, $id, $title) {
			return $this->service->rename($this->getUserId(), $wikiId, $id, $title);
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $wikiId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function destroy(int $wikiId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($wikiId, $id) {
			return $this->service->delete($this->getUserId(), $wikiId, $id);
		});
	}
}
