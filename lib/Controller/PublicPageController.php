<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Service\MissingDependencyException;
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

	/** @var PageService */
	private $service;

	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var LoggerInterface */
	private $logger;

	/** @var CollectiveShare */
	private $share;

	use ErrorHelper;

	public function __construct(string $appName,
								IRequest $request,
								CollectiveShareMapper $collectiveShareMapper,
								PageService $service,
								CollectiveMapper $collectiveMapper,
								ISession $session,
								LoggerInterface $logger) {
		parent::__construct($appName, $request, $session);
		$this->collectiveShareMapper = $collectiveShareMapper;
		$this->service = $service;
		$this->collectiveMapper = $collectiveMapper;
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
	 * @return CollectiveShare
	 * @throws NotFoundException
	 */
	private function getShare(): CollectiveShare {
		if (null === $this->share) {
			try {
				$this->share = $this->collectiveShareMapper->findOneByToken($this->getToken());
			} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
				throw new NotFoundException('Failed to get shared collective');
			}
		}

		return $this->share;
	}

	/**
	 * @return Collective
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getCollective(): Collective {
		if (null === $collective = $this->collectiveMapper->findById(
			$this->getShare()->getCollectiveId(),
			$this->getShare()->getOwner()
		)) {
			throw new NotFoundException('Failed to get shared collective');
		}

		return $collective;
	}

	/**
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function index(): DataResponse {
		return $this->handleErrorResponse(function (): array {
			$pages = $this->service->findAll($this->getShare()->getOwner(), $this->getCollective());
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
			$page = $this->service->find($this->getShare()->getOwner(), $this->getCollective(), $parentId, $id);
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
			$backlinks = $this->service->getBacklinks($this->getShare()->getOwner(), $this->getCollective(), $parentId, $id);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
