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
	use ErrorHelper;

	public function __construct(string $AppName,
		IRequest $request,
		private CollectiveService $collectiveService,
		private PageService $pageService,
		private IUserSession $userSession,
		private LoggerInterface $logger,
		private CollectiveShareService $shareService) {
		parent::__construct($AppName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function prepareResponse(Closure $callback) : DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getCollectiveShares(int $collectiveId): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId): array {
			$userId = $this->getUserId();
			$collective = $this->collectiveService->getCollective($collectiveId, $userId);
			$shares = $this->shareService->getSharesByCollectiveAndUser($userId, $collective->getId());
			return [
				"data" => $shares
			];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function createCollectiveShare(int $collectiveId, string $password = ''): DataResponse {
		return $this->createPageShare($collectiveId, 0, $password);
	}

	/**
	 * @NoAdminRequired
	 */
	public function updateCollectiveShare(int $collectiveId, string $token, bool $editable, string $password = ''): DataResponse {
		return $this->updatePageShare($collectiveId, 0, $token, $editable, $password);
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteCollectiveShare(int $collectiveId, string $token): DataResponse {
		return $this->deletePageShare($collectiveId, 0, $token);
	}

	/**
	 * @NoAdminRequired
	 */
	public function createPageShare(int $collectiveId, int $pageId = 0, string $password = ''): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId, $pageId, $password): array {
			$userId = $this->getUserId();
			$collective = $this->collectiveService->getCollective($collectiveId, $userId);
			$pageInfo = null;
			if ($pageId !== 0) {
				$pageInfo = $this->pageService->pageToSubFolder($collectiveId, $pageId, $userId);
			}
			$share = $this->shareService->createShare($userId, $collective, $pageInfo, $password);
			return [
				"data" => $share
			];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function updatePageShare(int $collectiveId, int $pageId, string $token, bool $editable, string $password = ''): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId, $pageId, $token, $editable, $password): array {
			$userId = $this->getUserId();
			$collective = $this->collectiveService->getCollective($collectiveId, $userId);
			$pageInfo = null;
			if ($pageId !== 0) {
				$pageInfo = $this->pageService->findByFileId($collectiveId, $pageId, $userId);
			}
			$share = $this->shareService->updateShare($userId, $collective, $pageInfo, $token, $editable, $password);
			return [
				"data" => $share
			];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function deletePageShare(int $collectiveId, int $pageId, string $token): DataResponse {
		return $this->prepareResponse(function () use ($collectiveId, $pageId, $token): array {
			$userId = $this->getUserId();
			$this->collectiveService->getCollective($collectiveId, $userId);
			$this->shareService->deleteShare($userId, $collectiveId, $pageId, $token);
			return [];
		});
	}
}
