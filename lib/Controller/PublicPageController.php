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

class PublicPageController extends PublicShareController {
	/** @var CollectiveShareMapper */
	private $collectiveShareMapper;

	/** @var CollectiveShareService */
	private $collectiveShareService;

	/** @var PageService */
	private $service;

	/** @var LoggerInterface */
	private $logger;

	/** @var CollectiveShareInfo */
	private $share;

	use ErrorHelper;

	public function __construct(string                $appName,
								IRequest              $request,
								CollectiveShareMapper $collectiveShareMapper,
								CollectiveShareService $collectiveShareService,
								PageService $service,
								ISession $session,
								LoggerInterface $logger) {
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
			$pages = $this->service->findAll($collectiveId, $owner);
			foreach ($pages as $page) {
				// Shares don't have a collective path
				$page->setCollectivePath('');
				$page->setShareToken($this->getToken());
			}
			return [
				"data" => $pages
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int    $parentId
	 * @param int    $id
	 *
	 * @return DataResponse
	 */
	public function get(int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id): array {
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$page = $this->service->find($collectiveId, $parentId, $id, $owner);
			// Shares don't have a collective path
			$page->setCollectivePath('');
			$page->setShareToken($this->getToken());
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int    $parentId
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function create(int $parentId, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $title): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$page = $this->service->create($collectiveId, $parentId, $title, $owner);
			// Shares don't have a collective path
			$page->setCollectivePath('');
			$page->setShareToken($this->getToken());
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function touch(int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$page = $this->service->touch($collectiveId, $parentId, $id, $owner);
			// Shares don't have a collective path
			$page->setCollectivePath('');
			$page->setShareToken($this->getToken());
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int    $parentId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $parentId, int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id, $title): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$page = $this->service->rename($collectiveId, $parentId, $id, $title, $owner);
			// Shares don't have a collective path
			$page->setCollectivePath('');
			$page->setShareToken($this->getToken());
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function delete(int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id): array {
			$this->checkEditPermissions();
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$page = $this->service->delete($collectiveId, $parentId, $id, $owner);
			// Shares don't have a collective path
			$page->setCollectivePath('');
			$page->setShareToken($this->getToken());
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @param int    $parentId
	 * @param int    $id
	 *
	 * @return DataResponse
	 */
	public function getBacklinks(int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id): array {
			$owner = $this->getShare()->getOwner();
			$collectiveId = $this->getShare()->getCollectiveId();
			$backlinks = $this->service->getBacklinks($collectiveId, $parentId, $id, $owner);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
