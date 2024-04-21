<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\PublicShareController;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class PublicPageTrashController extends PublicShareController {
	private ?IShare $share = null;
	private ?CollectiveShare $collectiveShare = null;

	use ErrorHelper;

	public function __construct(string $appName,
		IRequest $request,
		private ShareManager $shareManager,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveShareService $collectiveShareService,
		private PageService $service,
		ISession $session,
		private LoggerInterface $logger) {
		parent::__construct($appName, $request, $session);
	}

	/**
	 * @throws ShareNotFound
	 */
	protected function getShare(): IShare {
		if ($this->share === null) {
			$this->share = $this->shareManager->getShareByToken($this->getToken());
		}
		return $this->share;
	}

	/**
	 * @throws NotFoundException
	 */
	private function getCollectiveShare(): CollectiveShare {
		if ($this->collectiveShare === null) {
			$this->collectiveShare = $this->collectiveShareService->findShareByToken($this->getToken());

			if ($this->collectiveShare === null) {
				throw new NotFoundException('Failed to get shared collective');
			}

			if ($this->collectiveShare->getPageId() !== 0) {
				throw new NotFoundException('Shared page does not support page trash');
			}
		}

		return $this->collectiveShare;
	}

	protected function getPasswordHash(): string {
		return $this->getShare()->getPassword();
	}

	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException | MultipleObjectsReturnedException) {
			return false;
		}

		return true;
	}

	protected function isPasswordProtected(): bool {
		return $this->getShare()->getPassword() !== null;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function checkEditPermissions(): void {
		if (!$this->getCollectiveShare()->getEditable()) {
			throw new NotPermittedException('Not permitted to edit shared collective');
		}
	}

	/**
	 * @PublicPage
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function (): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$pageInfos = $this->service->findAllTrash($collectiveId, $owner);
			foreach ($pageInfos as $pageInfo) {
				// Shares don't have a collective path
				$pageInfo->setCollectivePath('');
				$pageInfo->setShareToken($this->getToken());
			}
			return [
				"data" => $pageInfos
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function restore(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$pageInfo = $this->service->restore($collectiveId, $id, $owner);
			// Shares don't have a collective path
			$pageInfo->setCollectivePath('');
			$pageInfo->setShareToken($this->getToken());
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function delete(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$this->service->delete($collectiveId, $id, $owner);
			return [];
		}, $this->logger);
	}
}
