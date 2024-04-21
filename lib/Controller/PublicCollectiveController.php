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
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class PublicCollectiveController extends PublicShareController {
	use ErrorHelper;

	private ?IShare $share = null;

	public function __construct(string $AppName,
		IRequest $request,
		private ShareManager $shareManager,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveService $service,
		ISession $session,
		private LoggerInterface $logger) {
		parent::__construct($AppName, $request, $session);
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

	private function prepareResponse(Closure $callback) : DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	/**
	 * @PublicPage
	 */
	public function get(): DataResponse {
		return $this->prepareResponse(function (): array {
			try {
				$share = $this->collectiveShareMapper->findOneByToken($this->getToken());
			} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
				throw new NotFoundException('Failed to get shared collective', 0, $e);
			}
			$collective = $this->service->getCollectiveWithShare($share->getCollectiveId(),
				$share->getOwner(),
				$share->getToken());
			// Explicitly set member level
			$collective->setLevel(Member::LEVEL_MEMBER);
			return [
				"data" => [$collective],
			];
		});
	}
}
