<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Model\CollectiveShareInfo;
use OCA\Collectives\Service\CollectiveShareService;
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

	/** @var CollectiveShareService */
	private $collectiveShareService;

	/** @var PageService */
	private $service;

	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var LoggerInterface */
	private $logger;

	/** @var CollectiveShareInfo */
	private $share;

	use PageActions;

	public function __construct(string $appName,
								IRequest $request,
								CollectiveShareMapper $collectiveShareMapper,
								CollectiveShareService $collectiveShareService,
								PageService $service,
								CollectiveMapper $collectiveMapper,
								ISession $session,
								LoggerInterface $logger) {
		parent::__construct($appName, $request, $session);
		$this->collectiveShareMapper = $collectiveShareMapper;
		$this->collectiveShareService = $collectiveShareService;
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
	 * @return string
	 */
	protected function getUserId(): string {
		return $this->getShare()->getOwner();
	}

	/**
	 * @return Collective
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function getCollective(): Collective {
		if (null === $collective = $this->collectiveMapper->findById(
			$this->getShare()->getCollectiveId(),
			$this->getShare()->getOwner()
		)) {
			throw new NotFoundException('Failed to get shared collective');
		}

		return $collective;
	}

	/**
	 * @return void
	 *
	 * @param PageFile $page
	 */
	protected function decoratePage($page): void {
		// Shares don't have a collective path
		$page->setCollectivePath('');
		$page->setShareToken($this->getToken());
	}

	/**
	 * @return PageService
	 */
	protected function getService(): PageService {
		return $this->service;
	}

	/**
	 * @return void
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	protected function checkEditPermissions(): void {
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
		return $this->pageIndex();
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
		return $this->getPage($parentId, $id);
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
		return $this->createPage($parentId, $title);
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
		return $this->touchPage($parentId, $id);
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
		return $this->renamePage($parentId, $id, $title);
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
		return $this->deletePage($parentId, $id, $title);
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
		return $this->getPageBacklinks($parentId, $id);
	}
}
