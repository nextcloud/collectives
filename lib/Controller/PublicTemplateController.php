<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\TemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to template pages of a public collective share.
 *
 * @psalm-import-type CollectivesPageInfo from ResponseDefinitions
 */
class PublicTemplateController extends CollectivesPublicOCSController {
	use OCSExceptionHelper;
	private ?CollectiveShare $collectiveShare = null;

	public function __construct(
		string $appName,
		IRequest $request,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveShareService $collectiveShareService,
		private TemplateService $templateService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
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

			if ($this->collectiveShare->getPageId() !== 0) {
				throw new OCSNotFoundException('Page share does not support templates');
			}
		}

		return $this->collectiveShare;
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
	 * @throws OCSForbiddenException
	 */
	private function checkEditPermissions(): void {
		if (!$this->getCollectiveShare()->getEditable()) {
			throw new OCSForbiddenException('Not permitted to edit shared collective');
		}
	}

	/**
	 * Get template pages of a collective
	 *
	 * @return DataResponse<Http::STATUS_OK, array{templates: list<CollectivesPageInfo>}, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Public collective/page share not found
	 *
	 * 200: Template pages returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 10)]
	public function index(): DataResponse {
		$templateInfos = $this->handleErrorResponse(function (): array {
			$owner = $this->getCollectiveShare()->getOwner();
			$collectiveId = $this->getCollectiveShare()->getCollectiveId();
			$templateInfos = $this->templateService->getTemplates($collectiveId, $owner);
			foreach ($templateInfos as $templateInfo) {
				// Shares don't have a collective path
				$templateInfo->setCollectivePath('');
				$templateInfo->setShareToken($this->getToken());
			}
			return $templateInfos;
		}, $this->logger);
		return new DataResponse(['templates' => $templateInfos]);
	}
}
