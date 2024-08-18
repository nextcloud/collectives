<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\CircleExistsException;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Constants;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

/**
 * Provides access to collectives.
 *
 * @psalm-import-type CollectivesCollective from ResponseDefinitions
 */
class CollectiveController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $AppName,
		IRequest $request,
		private CollectiveService $service,
		private IUserSession $userSession,
		private IFactory $l10nFactory,
		private LoggerInterface $logger,
		private NodeHelper $nodeHelper,
		private string $userId,
	) {
		parent::__construct($AppName, $request);
	}

	private function getUserLang(): string {
		return $this->l10nFactory->getUserLanguage($this->userSession->getUser());
	}

	/**
	 * Get collectives
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collectives: list<CollectivesCollective>}, array{}>
	 * @throws OCSNotFoundException Something not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Collectives returned
	 */
	#[NoAdminRequired]
	public function index(): DataResponse {
		$collectives = $this->handleErrorResponse(fn (): array => $this->service->getCollectivesWithShares($this->userId), $this->logger);
		return new DataResponse(['collectives' => $collectives]);
	}

	/**
	 * Create a collective
	 *
	 * @param string $name Name of the collective
	 * @param ?string $emoji Optional emoji
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collective: CollectivesCollective, info: string}, array{}>
	 * @throws OCSBadRequestException Collective or team already exists
	 * @throws OCSNotFoundException Something not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Collective created
	 */
	#[NoAdminRequired]
	public function create(string $name, ?string $emoji = null): DataResponse {
		try {
			[$collective, $info] = $this->handleErrorResponse(function () use ($name, $emoji): array {
				[$collective, $info] = $this->service->createCollective(
					$this->userId,
					$this->getUserLang(),
					$name,
					$emoji,
				);
				return [$collective, $info];
			}, $this->logger);
		} catch (CircleExistsException|UnprocessableEntityException $e) {
			$this->logger->debug('Collectives app team exists error: ' . $e->getMessage(), ['exception' => $e]);
			throw new OCSBadRequestException($e->getMessage());
		}
		return new DataResponse(['collective' => $collective, 'info' => $info]);
	}

	/**
	 * Update an existing collective
	 *
	 * @param int $id ID of the collective
	 * @param ?string $emoji Optional emoji
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collective: CollectivesCollective}, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Collective updated
	 */
	#[NoAdminRequired]
	public function update(int $id, ?string $emoji = null): DataResponse {
		$collective = $this->handleErrorResponse(fn (): Collective => $this->service->updateCollective(
			$id,
			$this->userId,
			$emoji
		), $this->logger);
		return new DataResponse(['collective' => $collective]);
	}

	/**
	 * Set edit level for an existing collective
	 *
	 * @param int $id ID of the collective
	 * @param int $level Edit level
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collective: CollectivesCollective}, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Collective updated
	 */
	#[NoAdminRequired]
	public function editLevel(int $id, int $level): DataResponse {
		$collective = $this->handleErrorResponse(fn (): Collective => $this->service->setPermissionLevel(
			$id,
			$this->userId,
			$level,
			Collective::editPermissions
		), $this->logger);
		return new DataResponse(['collective' => $collective]);
	}

	/**
	 * Set share level for an existing collective
	 *
	 * @param int $id ID of the collective
	 * @param int $level Share level
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collective: CollectivesCollective}, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Collective updated
	 */
	#[NoAdminRequired]
	public function shareLevel(int $id, int $level): DataResponse {
		$collective = $this->handleErrorResponse(fn (): Collective => $this->service->setPermissionLevel(
			$id,
			$this->userId,
			$level,
			Constants::PERMISSION_SHARE
		), $this->logger);
		return new DataResponse(['collective' => $collective]);
	}

	/**
	 * Set page mode for an existing collective
	 *
	 * @param int $id ID of the collective
	 * @param int $mode Page edit mode
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collective: CollectivesCollective}, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Collective updated
	 */
	#[NoAdminRequired]
	public function pageMode(int $id, int $mode): DataResponse {
		$collective = $this->handleErrorResponse(fn (): Collective => $this->service->setPageMode(
			$id,
			$this->userId,
			$mode,
		), $this->logger);
		return new DataResponse(['collective' => $collective]);
	}

	/**
	 * Trash an existing collective
	 *
	 * @param int $id ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collective: CollectivesCollective}, array{}>
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Collective trashed
	 */
	#[NoAdminRequired]
	public function trash(int $id): DataResponse {
		$collective = $this->handleErrorResponse(fn (): Collective => $this->service->trashCollective($id, $this->userId), $this->logger);
		return new DataResponse(['collective' => $collective]);
	}
}
