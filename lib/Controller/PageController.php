<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	/** @var PageService */
	private $service;

	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var IUserSession */
	private $userSession;

	/** @var LoggerInterface */
	private $logger;

	use ErrorHelper;

	public function __construct(string $appName,
								IRequest $request,
								PageService $service,
								CollectiveMapper $collectiveMapper,
								IUserSession $userSession,
								LoggerInterface $logger) {
		parent::__construct($appName, $request);
		$this->service = $service;
		$this->collectiveMapper = $collectiveMapper;
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
	 * @param int $collectiveId
	 *
	 * @return Collective
	 * @throws NotFoundException
	 */
	private function getCollective(int $collectiveId): Collective {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $this->getUserId())) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}

		return $collective;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 *
	 * @return DataResponse
	 */
	public function index(int $collectiveId): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId): array {
			$pages = $this->service->findAll($this->getUserId(), $this->getCollective($collectiveId));
			return [
				"data" => $pages
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function get(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$page = $this->service->find($this->getUserId(), $this->getCollective($collectiveId), $parentId, $id);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param int    $parentId
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function create(int $collectiveId, int $parentId, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $title): array {
			$page = $this->service->create($this->getUserId(), $this->getCollective($collectiveId), $parentId, $title);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function touch(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId,  $id): array {
			$page = $this->service->touch($this->getUserId(), $this->getCollective($collectiveId), $parentId, $id);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param int    $parentId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $collectiveId, int $parentId, int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id, $title): array {
			$page = $this->service->rename($this->getUserId(), $this->getCollective($collectiveId), $parentId, $id, $title);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function delete(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$page = $this->service->delete($this->getUserId(), $this->getCollective($collectiveId), $parentId, $id);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function getBacklinks(int $collectiveId, int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId, $parentId, $id): array {
			$backlinks = $this->service->getBacklinks($this->getUserId(), $this->getCollective($collectiveId), $parentId, $id);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
