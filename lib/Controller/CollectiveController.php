<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\CollectiveService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\Constants;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class CollectiveController extends Controller {
	use ErrorHelper;

	public function __construct(string $AppName,
		IRequest $request,
		private CollectiveService $service,
		private IUserSession $userSession,
		private IFactory $l10nFactory,
		private LoggerInterface $logger,
		private NodeHelper $nodeHelper) {
		parent::__construct($AppName, $request);
	}

	private function getUserId(): string {
		return $this->userSession->getUser()->getUID();
	}

	private function getUserLang(): string {
		return $this->l10nFactory->getUserLanguage($this->userSession->getUser());
	}

	private function prepareResponse(Closure $callback): DataResponse {
		return $this->handleErrorResponse($callback, $this->logger);
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(): DataResponse {
		return $this->prepareResponse(fn (): array => [
			"data" => $this->service->getCollectivesWithShares($this->getUserId()),
		]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(string $name, ?string $emoji = null): DataResponse {
		return $this->prepareResponse(function () use ($name, $emoji): array {
			$safeName = $this->nodeHelper->sanitiseFilename($name);
			[$collective, $info] = $this->service->createCollective(
				$this->getUserId(),
				$this->getUserLang(),
				$safeName,
				$emoji,
			);
			return [
				"data" => $collective,
				"message" => $info,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function update(int $id, ?string $emoji = null): DataResponse {
		return $this->prepareResponse(function () use ($id, $emoji): array {
			$collective = $this->service->updateCollective(
				$id,
				$this->getUserId(),
				$emoji
			);
			return [
				"data" => $collective,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function editLevel(int $id, int $level): DataResponse {
		return $this->prepareResponse(function () use ($id, $level): array {
			$collective = $this->service->setPermissionLevel(
				$id,
				$this->getUserId(),
				$level,
				Collective::editPermissions
			);
			return [
				"data" => $collective,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function shareLevel(int $id, int $level): DataResponse {
		return $this->prepareResponse(function () use ($id, $level): array {
			$collective = $this->service->setPermissionLevel(
				$id,
				$this->getUserId(),
				$level,
				Constants::PERMISSION_SHARE
			);
			return [
				"data" => $collective,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function pageMode(int $id, int $mode): DataResponse {
		return $this->prepareResponse(function () use ($id, $mode): array {
			$collective = $this->service->setPageMode(
				$id,
				$this->getUserId(),
				$mode,
			);
			return [
				"data" => $collective,
			];
		});
	}

	/**
	 * @NoAdminRequired
	 */
	public function trash(int $id): DataResponse {
		return $this->prepareResponse(function () use ($id): array {
			$collective = $this->service->trashCollective($id, $this->getUserId());
			return [
				"data" => $collective,
			];
		});
	}
}
