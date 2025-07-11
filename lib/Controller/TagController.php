<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Tag;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\TagExistsException;
use OCA\Collectives\Service\TagService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to tags for pages.
 *
 * @psalm-import-type CollectivesTag from ResponseDefinitions
 */
class TagController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $AppName,
		IRequest $request,
		private TagService $service,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * Get tags
	 *
	 * @param int $collectiveId ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{tags: list<CollectivesTag>}, array{}>
	 * @throws OCSNotFoundException Something not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Tags returned
	 */
	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$tags = $this->handleErrorResponse(fn (): array => $this->service->index($collectiveId, $this->userId), $this->logger);
		return new DataResponse(['tags' => $tags]);
	}

	/**
	 * Create a tag
	 *
	 * @param int $collectiveId ID of the collective
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
	#[NoAdminRequired]
	public function create(int $collectiveId, string $name, string $color): DataResponse {
		try {
			$tag = $this->handleErrorResponse(fn (): Tag => $this->service->create(
				$collectiveId,
				$this->userId,
				$name,
				$color,
			), $this->logger);
		} catch (TagExistsException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}
		return new DataResponse(['tag' => $tag]);
	}

	/**
	 * Update an existing tag
	 *
	 * @param int $collectiveId ID of the collective
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
	#[NoAdminRequired]
	public function update(int $collectiveId, int $id, string $name, string $color): DataResponse {
		$tag = $this->handleErrorResponse(fn (): Tag => $this->service->update(
			$collectiveId,
			$this->userId,
			$id,
			$name,
			$color,
		), $this->logger);
		return new DataResponse(['tag' => $tag]);
	}

	/**
	 * Delete a tag
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the tag
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSNotFoundException Collective or tag not found
	 * @throws OCSForbiddenException Not permitted
	 *
	 * 200: Tag deleted
	 */
	#[NoAdminRequired]
	public function delete(int $collectiveId, int $id): DataResponse {
		$this->handleErrorResponse(fn () => $this->service->delete($collectiveId, $this->userId, $id), $this->logger);
		return new DataResponse([]);
	}
}
