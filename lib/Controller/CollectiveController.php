<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\CollectiveService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class CollectiveController extends Controller {
	/** @var CollectiveService */
	private $service;

	/** @var IUserSession */
	private $userSession;

	/** @var IFactory */
	private $l10nFactory;

	/** @var LoggerInterface */
	private $logger;

	/** @var NodeHelper */
	private $nodeHelper;

	use ErrorHelper;

	public function __construct(string $AppName,
								IRequest $request,
								CollectiveService $service,
								IUserSession $userSession,
								IFactory $l10nFactory,
								LoggerInterface $logger,
								NodeHelper $nodeHelper) {
		parent::__construct($AppName, $request);
		$this->service = $service;
		$this->userSession = $userSession;
		$this->l10nFactory = $l10nFactory;
		$this->logger = $logger;
		$this->nodeHelper = $nodeHelper;
	}

	/**
	 * @return string
	 */
	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	/**
	 * @return string
	 */
	private function getUserLang(): string {
		return $this->l10nFactory->getUserLanguage($this->userSession->getUser());
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function () {
			return $this->service->getCollectives($this->getUserId());
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function indexTrash(): DataResponse {
		return $this->handleErrorResponse(function () {
			return $this->service->getCollectivesTrash($this->getUserId());
		}, $this->logger);
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
			$safeName = $this->nodeHelper->sanitiseFilename($name);
			return $this->service->createCollective(
				$this->getUserId(),
				$this->getUserLang(),
				$name,
				$safeName
			);
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function trash(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id) {
			return $this->service->trashCollective($this->getUserId(), $id);
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function delete(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id) {
			return $this->service->deleteCollective($this->getUserId(), $id, false);
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function deleteAll(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id) {
			return $this->service->deleteCollective($this->getUserId(), $id, true);
		}, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function restore(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id) {
			return $this->service->restoreCollective($this->getUserId(), $id);
		}, $this->logger);
	}
}
