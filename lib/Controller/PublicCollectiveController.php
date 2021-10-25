<?php

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Circles\Model\Member;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\PublicShareController;
use OCP\IRequest;
use OCP\ISession;
use Psr\Log\LoggerInterface;

class PublicCollectiveController extends PublicShareController {
	/** @var CollectiveShareMapper */
	private $collectiveShareMapper;

	/** @var CollectiveService */
	private $service;

	/** @var LoggerInterface */
	private $logger;

	use ErrorHelper;

	public function __construct(string $AppName,
								IRequest $request,
								CollectiveShareMapper $collectiveShareMapper,
								CollectiveService $service,
								ISession $session,
								LoggerInterface $logger) {
		parent::__construct($AppName, $request, $session);
		$this->collectiveShareMapper = $collectiveShareMapper;
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
			$this->collectiveShareMapper->findByToken($this->getToken());
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
	 * @param Closure $callback
	 *
	 * @return DataResponse
	 */
	private function prepareResponse(Closure $callback) : DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	/**
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function get(): DataResponse {
		return $this->prepareResponse(function () {
			try {
				$share = $this->collectiveShareMapper->findByToken($this->getToken());
			} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
				throw new NotFoundException('Failed to get shared collective');
			}
			$collective = $this->service->getCollective($share->getOwner(),
				$share->getCollectiveId());
			$collective->setShareToken($this->getToken());
			// Explicitly set member level
			$collective->setLevel(Member::LEVEL_MEMBER);
			return [
				"data" => [$collective],
			];
		});
	}
}
