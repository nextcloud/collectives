<?php

namespace OCA\Collectives\Controller;

use Closure;

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
	 * @param Closure              $callback
	 *
	 * @return DataResponse
	 */
	private function prepareResponse(Closure $callback) : DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function index(): DataResponse {
		return $this->prepareResponse(function () {
			return [
				"data" => $this->service->getCollectives($this->getUserId()),
			];
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
		return $this->prepareResponse(function () use ($name) {
			$safeName = $this->nodeHelper->sanitiseFilename($name);
			[$collective, $info] = $this->service->createCollective(
				$this->getUserId(),
				$this->getUserLang(),
				$safeName
			);
			return [
				"data" => $collective,
				"message" => $info,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function trash(int $id): DataResponse {
		return $this->prepareResponse(function () use ($id) {
			$collective = $this->service->trashCollective($this->getUserId(), $id);
			return [
				"data" => $collective
			];
		});
	}
}
