<?php

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\CollectiveShareService;
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

	/** @var CollectiveShareService */
	private $shareService;

	use ErrorHelper;

	public function __construct(string $AppName,
								IRequest $request,
								CollectiveService $service,
								IUserSession $userSession,
								IFactory $l10nFactory,
								LoggerInterface $logger,
								NodeHelper $nodeHelper,
								CollectiveShareService $shareService) {
		parent::__construct($AppName, $request);
		$this->service = $service;
		$this->userSession = $userSession;
		$this->l10nFactory = $l10nFactory;
		$this->logger = $logger;
		$this->nodeHelper = $nodeHelper;
		$this->shareService = $shareService;
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
		return $this->prepareResponse(function (): array {
			return [
				"data" => $this->service->getCollectivesWithShares($this->getUserId()),
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string      $name
	 * @param string|null $emoji
	 *
	 * @return DataResponse
	 */
	public function create(string $name, string $emoji = null): DataResponse {
		return $this->prepareResponse(function () use ($name, $emoji): array {
			$safeName = $this->nodeHelper->sanitiseFilename($name);
			[$collective, $info] = $this->service->createCollective(
				$this->getUserId(),
				$this->getUserLang(),
				$safeName,
				$emoji
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
	 * @param int         $id
	 * @param string|null $emoji
	 *
	 * @return DataResponse
	 */
	public function update(int $id, string $emoji = null): DataResponse {
		return $this->prepareResponse(function () use ($id, $emoji): array {
			$collective = $this->service->updateCollective(
				$this->getUserId(),
				$id,
				$emoji
			);
			return [
				"data" => $collective,
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
		return $this->prepareResponse(function () use ($id): array {
			$collective = $this->service->trashCollective($this->getUserId(), $id);
			return [
				"data" => $collective
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
	public function createShare(int $id): DataResponse {
		return $this->prepareResponse(function () use ($id): array {
			$userId = $this->getUserId();
			$collective = $this->service->getCollective($userId, $id);
			$share = $this->shareService->createShare($userId, $collective);
			$collective->setShareToken($share->getToken());
			return [
				"data" => $collective
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $id
	 * @param string $token
	 *
	 * @return DataResponse
	 */
	public function deleteShare(int $id, string $token): DataResponse {
		return $this->prepareResponse(function () use ($id, $token): array {
			$userId = $this->getUserId();
			$collective = $this->service->getCollective($userId, $id);
			$this->shareService->deleteShare($userId, $id, $token);
			return [
				"data" => $collective
			];
		});
	}
}
