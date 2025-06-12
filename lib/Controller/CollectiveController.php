<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\CircleExistsException;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Constants;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class CollectiveController extends Controller {
	use ErrorHelper;

	public function __construct(
		string $AppName,
		IRequest $request,
		private CollectiveService $service,
		private IUserSession $userSession,
		private IFactory $l10nFactory,
		private LoggerInterface $logger,
		private NodeHelper $nodeHelper,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @throws NotFoundException
	 */
	private function getUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new NotFoundException('Session user not found');
		}
		return $user->getUID();
	}

	private function getUserLang(): string {
		return $this->l10nFactory->getUserLanguage($this->userSession->getUser());
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$collectives = $this->handleErrorResponse(function (): array {
			return $this->service->getCollectivesWithShares($this->getUserId());
		}, $this->logger);
		return new DataResponse(['data' => $collectives]);
	}

	#[NoAdminRequired]
	public function create(string $name, ?string $emoji = null): DataResponse {
		try {
			[$collective, $info] = $this->handleErrorResponse(function () use ($name, $emoji): array {
				$safeName = $this->nodeHelper->sanitiseFilename($name);
				[$collective, $info] = $this->service->createCollective(
					$this->getUserId(),
					$this->getUserLang(),
					$safeName,
					$emoji,
				);
				return [$collective, $info];
			}, $this->logger);
		} catch (CircleExistsException|UnprocessableEntityException $e) {
			$this->logger->debug('Collectives app CircleExists Error: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		return new DataResponse(['data' => $collective, 'info' => $info]);
	}

	#[NoAdminRequired]
	public function update(int $id, ?string $emoji = null): DataResponse {
		$collective = $this->handleErrorResponse(function () use ($id, $emoji): Collective {
			return $this->service->updateCollective(
				$id,
				$this->getUserId(),
				$emoji
			);
		}, $this->logger);
		return new DataResponse(['data' => $collective]);
	}

	#[NoAdminRequired]
	public function editLevel(int $id, int $level): DataResponse {
		$collective = $this->handleErrorResponse(function () use ($id, $level): Collective {
			return $this->service->setPermissionLevel(
				$id,
				$this->getUserId(),
				$level,
				Collective::editPermissions
			);
		}, $this->logger);
		return new DataResponse(['data' => $collective]);
	}

	#[NoAdminRequired]
	public function shareLevel(int $id, int $level): DataResponse {
		$collective = $this->handleErrorResponse(function () use ($id, $level): Collective {
			return $this->service->setPermissionLevel(
				$id,
				$this->getUserId(),
				$level,
				Constants::PERMISSION_SHARE
			);
		}, $this->logger);
		return new DataResponse(['data' => $collective]);
	}

	#[NoAdminRequired]
	public function pageMode(int $id, int $mode): DataResponse {
		$collective = $this->handleErrorResponse(function () use ($id, $mode): Collective {
			return $this->service->setPageMode(
				$id,
				$this->getUserId(),
				$mode,
			);
		}, $this->logger);
		return new DataResponse(['data' => $collective]);
	}

	#[NoAdminRequired]
	public function trash(int $id): DataResponse {
		$collective = $this->handleErrorResponse(function () use ($id): Collective {
			return $this->service->trashCollective($id, $this->getUserId());
		}, $this->logger);
		return new DataResponse(['data' => $collective]);
	}
}
