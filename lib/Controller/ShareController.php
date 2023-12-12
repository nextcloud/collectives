<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class ShareController extends Controller {
	private CollectiveService $collectiveService;
	private PageService $pageService;
	private IUserSession $userSession;
	private LoggerInterface $logger;
	private CollectiveShareService $shareService;

	use ErrorHelper;

	public function __construct(string $AppName,
		IRequest $request,
		CollectiveService $collectiveService,
		PageService $pageService,
		IUserSession $userSession,
		LoggerInterface $logger,
		CollectiveShareService $shareService) {
		parent::__construct($AppName, $request);
		$this->collectiveService = $collectiveService;
		$this->pageService = $pageService;
		$this->userSession = $userSession;
		$this->logger = $logger;
		$this->shareService = $shareService;
	}

	/**
	 * @return string
	 */
	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
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
	 * @param int $collectiveId
	 *
	 * @return DataResponse
	 */
	public function getCollectiveShares(int $collectiveId): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId): array {
			$userId = $this->getUserId();
			$collectiveInfo = $this->collectiveService->getCollectiveInfo($collectiveId, $userId);
			$shares = $this->shareService->getSharesByCollectiveAndUser($userId, $collectiveInfo->getId());
			return [
				"data" => $shares
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 *
	 * @return DataResponse
	 */
	public function createCollectiveShare(int $collectiveId): DataResponse {
		return $this->createPageShare($collectiveId, 0);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param string $token
	 * @param bool   $editable
	 *
	 * @return DataResponse
	 */
	public function updateCollectiveShare(int $collectiveId, string $token, bool $editable): DataResponse {
		return $this->updatePageShare($collectiveId, 0, $token, $editable);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param string $token
	 *
	 * @return DataResponse
	 */
	public function deleteCollectiveShare(int $collectiveId, string $token): DataResponse {
		return $this->deletePageShare($collectiveId, 0, $token);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $pageId
	 *
	 * @return DataResponse
	 */
	public function createPageShare(int $collectiveId, int $pageId = 0): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId, $pageId): array {
			$userId = $this->getUserId();
			$collectiveInfo = $this->collectiveService->getCollectiveInfo($collectiveId, $userId);
			$pageInfo = null;
			if ($pageId !== 0) {
				$pageInfo = $this->pageService->findByFileId($collectiveId, $pageId, $userId);
			}
			$share = $this->shareService->createShare($userId, $collectiveInfo, $pageInfo);
			return [
				"data" => $share
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param int    $pageId
	 * @param string $token
	 * @param bool   $editable
	 *
	 * @return DataResponse
	 */
	public function updatePageShare(int $collectiveId, int $pageId, string $token, bool $editable = false): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId, $pageId, $token, $editable): array {
			$userId = $this->getUserId();
			$collectiveInfo = $this->collectiveService->getCollectiveInfo($collectiveId, $userId);
			$pageInfo = null;
			if ($pageId !== 0) {
				$pageInfo = $this->pageService->findByFileId($collectiveId, $pageId, $userId);
			}
			$share = $this->shareService->updateShare($userId, $collectiveInfo, $pageInfo, $token, $editable);
			return [
				"data" => $share
			];
		});
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param int    $pageId
	 * @param string $token
	 *
	 * @return DataResponse
	 */
	public function deletePageShare(int $collectiveId, int $pageId, string $token): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId, $pageId, $token): array {
			$userId = $this->getUserId();
			$this->collectiveService->getCollectiveInfo($collectiveId, $userId);
			$this->shareService->deleteShare($userId, $collectiveId, $pageId, $token);
			return [];
		});
	}
}
