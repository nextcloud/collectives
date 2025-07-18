<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Db\Tag;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\TagExistsException;
use OCA\Collectives\Service\TagService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Provides access to tags for pages.
 *
 * @psalm-import-type CollectivesTag from ResponseDefinitions
 */
class PublicTagController extends CollectivesPublicOCSController {
	use OCSExceptionHelper;

	private ?IShare $share = null;
	private ?CollectiveShare $collectiveShare = null;

	public function __construct(
		string $AppName,
		IRequest $request,
		private ShareManager $shareManager,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveShareService $collectiveShareService,
		private TagService $service,
		ISession $session,
		private LoggerInterface $logger,
	) {
		parent::__construct($AppName, $request, $session);
	}

	/**
	 * @throws OCSNotFoundException
	 */
	protected function getShare(): IShare {
		if ($this->share === null) {
			try {
				$this->share = $this->shareManager->getShareByToken($this->getToken());
			} catch (ShareNotFound $e) {
				throw new OCSNotFoundException($e->getMessage());
			}
		}
		return $this->share;
	}

	/**
	 * @throws OCSNotFoundException
	 */
	private function getCollectiveShare(): CollectiveShare {
		if ($this->collectiveShare === null) {
			$this->collectiveShare = $this->collectiveShareService->findShareByToken($this->getToken());

			if ($this->collectiveShare === null) {
				throw new OCSNotFoundException('Failed to get shared collective');
			}
		}

		return $this->collectiveShare;
	}

	/**
	 * @psalm-suppress InvalidNullableReturnType
	 * @psalm-suppress NullableReturnStatement
	 * @throws OCSNotFoundException
	 */
	protected function getPasswordHash(): string {
		return $this->getShare()->getPassword();
	}

	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return false;
		}

		return true;
	}

	/**
	 * @throws OCSNotFoundException
	 */
	protected function isPasswordProtected(): bool {
		return $this->getShare()->getPassword() !== null;
	}

	/**
	 * @throws OCSNotFoundException
	 * @throws OCSForbiddenException
	 */
	private function checkEditPermissions(): void {
		if (!$this->getCollectiveShare()->getEditable()) {
			throw new OCSForbiddenException('Not permitted to edit shared collective');
		}
	}

	/**
	 * Get tags
	 *
	 * @return DataResponse<Http::STATUS_OK, array{tags: list<CollectivesTag>}, array{}>
	 * @throws OCSNotFoundException Something not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Tags returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function index(): DataResponse {
		$tags = $this->handleErrorResponse(function (): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			return $this->service->index($collectiveId, $owner);
		}, $this->logger);
		return new DataResponse(['tags' => $tags]);
	}

	/**
	 * Create a tag
	 *
	 * @param string $name Name of the tag
	 * @param string $color Color of the tag
	 *
	 * @return DataResponse<Http::STATUS_OK, array{tag: CollectivesTag, info: string}, array{}>
	 * @throws OCSBadRequestException Tag already exists for the collective
	 * @throws OCSNotFoundException Collective not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Tag created
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function create(string $name, string $color): DataResponse {
		try {
			$tag = $this->handleErrorResponse(function () use ($name, $color): Tag {
				$this->checkEditPermissions();
				$owner = $this->getCollectiveShare()->getOwner();
				$collectiveId = $this->getCollectiveShare()->getCollectiveId();
				return $this->service->create(
					$collectiveId,
					$owner,
					$name,
					$color,
				);
			}, $this->logger);
		} catch (TagExistsException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
		return new DataResponse(['tag' => $tag]);
	}

	/**
	 * Update an existing tag
	 *
	 * @param int $id ID of the tag
	 * @param string $name Name of the tag
	 * @param string $color Color of the tag
	 *
	 * @return DataResponse<Http::STATUS_OK, array{tag: CollectivesTag}, array{}>
	 * @throws OCSNotFoundException Collective or tag not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Tag updated
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function update(int $id, string $name, string $color): DataResponse {
		$tag = $this->handleErrorResponse(function () use ($id, $name, $color): Tag {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			return $this->service->update(
				$collectiveId,
				$owner,
				$id,
				$name,
				$color,
			);
		}, $this->logger);
		return new DataResponse(['tag' => $tag]);
	}

	/**
	 * Delete a tag
	 *
	 * @param int $id ID of the tag
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSNotFoundException Collective or tag not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Tag deleted
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function delete(int $id): DataResponse {
		$this->handleErrorResponse(function () use ($id) {
			$this->checkEditPermissions();
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$this->service->delete($collectiveId, $owner, $id);
		}, $this->logger);
		return new DataResponse([]);
	}
}
