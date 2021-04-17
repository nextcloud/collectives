<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	/** @var PageService */
	private $service;

	/** @var IUserSession */
	private $userSession;

	/** @var LoggerInterface */
	private $logger;

	use ErrorHelper;

	public function __construct(string $appName,
								IRequest $request,
								PageService $service,
								IUserSession $userSession,
								LoggerInterface $logger) {
		parent::__construct($appName, $request);
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
	 * @param int $collectiveId
	 */
	public function index(int $collectiveId): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId) {
			$pages = $this->service->findAll($this->getUserId(), $collectiveId);
			return [
				"data" => $pages
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function get(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id) {
			$page = $this->service->find($this->getUserId(), $collectiveId, $id);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function create(int $collectiveId, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $title) {
			$page = $this->service->create($this->getUserId(), $collectiveId, $title);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function touch(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id) {
			$page = $this->service->touch($this->getUserId(), $collectiveId, $id);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $collectiveId, int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id, $title) {
			$page = $this->service->rename($this->getUserId(), $collectiveId, $id, $title);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function destroy(int $collectiveId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $id) {
			$page = $this->service->delete($this->getUserId(), $collectiveId, $id);
			return [
				"data" => $page
			];
		}, $this->logger);
	}
}
