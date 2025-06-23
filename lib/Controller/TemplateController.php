<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\TemplateService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Provides access to template pages of a collective.
 *
 * @psalm-import-type CollectivesPageInfo from ResponseDefinitions
 */
class TemplateController extends OCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private TemplateService $templateService,
		private LoggerInterface $logger,
		private string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get template pages of a collective
	 *
	 * @param int $collectiveId ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{templates: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective not found
	 *
	 * 200: Template pages returned
	 */
	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$templateInfos = $this->handleErrorResponse(fn (): array => $this->templateService->getTemplates($collectiveId, $this->userId), $this->logger);
		return new DataResponse(['templates' => $templateInfos]);
	}

	/**
	 * Create a new template page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $parentId ID of the parent template page
	 * @param string $title Title of the page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{template: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or parent template page not found
	 *
	 * 200: New template page created
	 */
	#[NoAdminRequired]
	public function create(int $collectiveId, string $title, int $parentId): DataResponse {
		$templateInfo = $this->handleErrorResponse(fn (): PageInfo => $this->templateService->create($collectiveId, $parentId, $title, $this->userId), $this->logger);
		return new DataResponse(['template' => $templateInfo]);
	}

	/**
	 * Permanently delete a template page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the template page
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Template page deleted
	 */
	#[NoAdminRequired]
	public function delete(int $collectiveId, int $id): DataResponse {
		$this->handleErrorResponse(function () use ($collectiveId, $id): void {
			$this->templateService->delete($collectiveId, $id, $this->userId);
		}, $this->logger);
		return new DataResponse([]);
	}

	/**
	 * Rename a template page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the template page
	 * @param string $title New title of the template page
	 *
	 * @return DataResponse<Http::STATUS_OK, array{template: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Template page renamed
	 */
	#[NoAdminRequired]
	public function rename(int $collectiveId, int $id, string $title): DataResponse {
		$templateInfo = $this->handleErrorResponse(fn (): PageInfo => $this->templateService->rename($collectiveId, $id, $title, $this->userId), $this->logger);
		return new DataResponse(['template' => $templateInfo]);
	}

	/**
	 * Set/unset emoji for a template page
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the template page
	 * @param ?string $emoji Emoji to set or null to unset (optional, default null)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{template: CollectivesPageInfo}, array{}>
	 * @throws OCSForbiddenException Not Permitted
	 * @throws OCSNotFoundException Collective or page not found
	 *
	 * 200: Emoji set/unset
	 */
	#[NoAdminRequired]
	public function setEmoji(int $collectiveId, int $id, ?string $emoji = null): DataResponse {
		$templateInfo = $this->handleErrorResponse(fn (): PageInfo => $this->templateService->setEmoji($collectiveId, $id, $emoji, $this->userId), $this->logger);
		return new DataResponse(['template' => $templateInfo]);
	}
}
