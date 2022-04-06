<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class PageController extends Controller {
	/** @var PageService */
	private $service;

	/** @var int */
	private $collectiveId;

	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var IUserSession */
	private $userSession;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(string $appName,
								IRequest $request,
								PageService $service,
								CollectiveMapper $collectiveMapper,
								IUserSession $userSession,
								LoggerInterface $logger) {
		parent::__construct($appName, $request);
		$this->service = $service;
		$this->collectiveMapper = $collectiveMapper;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * @return string
	 */
	protected function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	/**
	 * @param int $collectiveId
	 *
	 * @return Collective
	 * @throws NotFoundException
	 */
	protected function getCollective(): Collective {
		if (null === $collective = $this->collectiveMapper->findById($this->collectiveId, $this->getUserId())) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}
		return $collective;
	}

	/**
	 * @return void
	 *
	 * @param PageFile $page
	 */
	protected function decoratePage($page): void {
		// noop - we can return the page as is.
	}

	/**
	 * @return PageService
	 */
	protected function getService(): PageService {
		return $this->service;
	}

	/**
	 * @return void
	 */
	protected function checkEditPermissions(): void {
		// noop - permission checks are performed by the service in the actions.
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 *
	 * @return DataResponse
	 */
	public function index(int $collectiveId): DataResponse {
		$this->collectiveId = $collectiveId;
		return $this->pageIndex();
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function get(int $collectiveId, int $parentId, int $id): DataResponse {
		$this->collectiveId = $collectiveId;
		return $this->getPage($parentId, $id);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param int    $parentId
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function create(int $collectiveId, int $parentId, string $title): DataResponse {
		$this->collectiveId = $collectiveId;
		return $this->createPage($parentId, $title);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function touch(int $collectiveId, int $parentId, int $id): DataResponse {
		$this->collectiveId = $collectiveId;
		return $this->touchPage($parentId, $id);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int    $collectiveId
	 * @param int    $parentId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function rename(int $collectiveId, int $parentId, int $id, string $title): DataResponse {
		$this->collectiveId = $collectiveId;
		return $this->renamePage($parentId, $id, $title);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function delete(int $collectiveId, int $parentId, int $id): DataResponse {
		$this->collectiveId = $collectiveId;
		return $this->deletePage($parentId, $id, $title);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $collectiveId
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function getBacklinks(int $collectiveId, int $parentId, int $id): DataResponse {
		$this->collectiveId = $collectiveId;
		return $this->getPageBacklinks($parentId, $id);
	}
}
