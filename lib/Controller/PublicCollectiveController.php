<?php

declare(strict_types=1);

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
	private CollectiveShareMapper $collectiveShareMapper;
	private CollectiveService $service;
	private LoggerInterface $logger;

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
	 * @param int $pageId
	 *
	 * @return DataResponse
	 */
	public function get(int $pageId = 0): DataResponse {
		return $this->prepareResponse(function () use ($pageId): array {
			try {
				$share = $this->collectiveShareMapper->findOneByToken($this->getToken());
			} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
				throw new NotFoundException('Failed to get shared collective', 0, $e);
			}
			$collective = $this->service->getCollectiveWithShare($share->getCollectiveId(),
				$share->getPageId(),
				$share->getOwner());
			// Explicitly set member level
			$collective->setLevel(Member::LEVEL_MEMBER);
			return [
				"data" => [$collective],
			];
		});
	}
}
