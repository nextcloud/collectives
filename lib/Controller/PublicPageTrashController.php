<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Model\CollectiveShareInfo;
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
use Psr\Log\LoggerInterface;

class PublicPageTrashController extends PublicShareController {
	private CollectiveShareMapper $collectiveShareMapper;
	private CollectiveShareService $collectiveShareService;
	private PageService $service;
	private LoggerInterface $logger;
	private ?CollectiveShareInfo $share = null;

	use ErrorHelper;

	public function __construct(string                 $appName,
		IRequest               $request,
		CollectiveShareMapper  $collectiveShareMapper,
		CollectiveShareService $collectiveShareService,
		PageService            $service,
		ISession               $session,
		LoggerInterface        $logger) {
		parent::__construct($appName, $request, $session);
		$this->collectiveShareMapper = $collectiveShareMapper;
		$this->collectiveShareService = $collectiveShareService;
		$this->service = $service;
		$this->logger = $logger;
	}

	/**
	 * @return string
	 */
	protected function getPasswordHash(): string {
		return '';
	}

	/**
	 * @return bool
	 */
	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function isPasswordProtected(): bool {
		return false;
	}

	/**
	 * @return CollectiveShareInfo
	 * @throws NotFoundException
	 */
	private function getShare(): CollectiveShareInfo {
		if (null === $this->share) {
			$this->share = $this->collectiveShareService->findShareByToken($this->getToken());

			if ($this->share === null) {
				throw new NotFoundException('Failed to get shared collective');
			}
		}

		return $this->share;
	}

	/**
	 * @return void
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function checkEditPermissions(): void {
		if (!$this->getShare()->getEditable()) {
			throw new NotPermittedException('Not permitted to edit shared collective');
		}
	}

	/**
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function (): array {
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$pageInfos = $this->service->findAllTrash($collectiveId, $owner);
			return [
				"data" => $pageInfos
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function restore(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$pageInfo = $this->service->restore($collectiveId, $id, $owner);
			return [
				"data" => $pageInfo
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function delete(int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($id): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$this->service->delete($collectiveId, $id, $owner);
			return [];
		}, $this->logger);
	}
}
