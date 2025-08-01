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
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Provides access to template pages of a public collective share.
 *
 * @psalm-import-type CollectivesPageInfo from ResponseDefinitions
 */
class PublicTemplateController extends CollectivesPublicOCSController {
	use OCSExceptionHelper;

	private ?IShare $share = null;
	private ?CollectiveShare $collectiveShare = null;

	public function __construct(
		string $appName,
		IRequest $request,
		private ShareManager $shareManager,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveShareService $collectiveShareService,
		private TemplateService $templateService,
		ISession $session,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request, $session);
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

			if ($this->collectiveShare->getPageId() !== 0) {
				throw new OCSNotFoundException('Page share does not support templates');
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
