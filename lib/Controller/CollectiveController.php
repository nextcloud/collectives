<?php

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\CollectiveShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\Constants;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class CollectiveController extends Controller {
	private CollectiveService $service;
	private IUserSession $userSession;
	private IFactory $l10nFactory;
	private LoggerInterface $logger;
	private NodeHelper $nodeHelper;
	private CollectiveShareService $shareService;

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
	 * @param Closure $callback
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
			[$collectiveInfo, $info] = $this->service->createCollective(
				$this->getUserId(),
				$this->getUserLang(),
				$safeName,
				$emoji
			);
			return [
				"data" => $collectiveInfo,
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
			$collectiveInfo = $this->service->updateCollective(
				$id,
				$this->getUserId(),
				$emoji
			);
			return [
				"data" => $collectiveInfo,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param int $level
	 *
	 * @return DataResponse
	 */
	public function editLevel(int $id, int $level): DataResponse {
		return $this->prepareResponse(function () use ($id, $level): array {
			$collectiveInfo = $this->service->setPermissionLevel(
				$id,
				$this->getUserId(),
				$level,
				Collective::editPermissions
			);
			return [
				"data" => $collectiveInfo,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param int $level
	 *
	 * @return DataResponse
	 */
	public function shareLevel(int $id, int $level): DataResponse {
		return $this->prepareResponse(function () use ($id, $level): array {
			$collectiveInfo = $this->service->setPermissionLevel(
				$id,
				$this->getUserId(),
				$level,
				Constants::PERMISSION_SHARE
			);
			return [
				"data" => $collectiveInfo,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param int $mode
	 *
	 * @return DataResponse
	 */
	public function pageMode(int $id, int $mode): DataResponse {
		return $this->prepareResponse(function () use ($id, $mode): array {
			$collectiveInfo = $this->service->setPageMode(
				$id,
				$this->getUserId(),
				$mode,
			);
			return [
				"data" => $collectiveInfo,
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
			$collectiveInfo = $this->service->trashCollective($id, $this->getUserId());
			return [
				"data" => $collectiveInfo
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
			$collectiveInfo = $this->service->getCollectiveInfo($id, $userId);
			$share = $this->shareService->createShare($userId, $collectiveInfo);
			$collectiveInfo->setShareToken($share->getToken());
			return [
				"data" => $collectiveInfo
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $id
	 * @param string $token
	 * @param bool   $editable
	 *
	 * @return DataResponse
	 */
	public function updateShare(int $id, string $token, bool $editable = false): DataResponse {
		return $this->prepareResponse(function () use ($id, $token, $editable): array {
			$userId = $this->getUserId();
			$collectiveInfo = $this->service->getCollectiveInfo($id, $userId);
			$this->shareService->updateShare($userId, $collectiveInfo, $token, $editable);
			$collectiveInfo = $this->service->getCollectiveWithShare($id, $userId);
			return [
				"data" => $collectiveInfo
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
			$collectiveInfo = $this->service->getCollectiveInfo($id, $userId);
			$this->shareService->deleteShare($userId, $id, $token);
			return [
				"data" => $collectiveInfo
			];
		});
	}
}
