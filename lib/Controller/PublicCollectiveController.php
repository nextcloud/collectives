<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Provides access to public collective and page shares.
 *
 * @psalm-import-type CollectivesCollective from ResponseDefinitions
 */
class PublicCollectiveController extends CollectivesPublicOCSController {
	use OCSExceptionHelper;

	private string $token;
	private ?IShare $share = null;

	public function __construct(
		string $AppName,
		IRequest $request,
		private ShareManager $shareManager,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveService $service,
		ISession $session,
		private LoggerInterface $logger,
	) {
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

	/**
	 * @psalm-suppress InvalidNullableReturnType
	 * @psalm-suppress NullableReturnStatement
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

	protected function isPasswordProtected(): bool {
		return $this->getShare()->getPassword() !== null;
	}

	/**
	 * @param string $token Share token of the public share
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collectives: list<CollectivesCollective>}, array{}>
	 * @throws OCSForbiddenException
	 * @throws OCSNotFoundException
	 *
	 * 200: Collective returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 60)]
	public function get(string $token): DataResponse {
		$this->setToken($token);
		$collective = $this->handleErrorResponse(function (): Collective {
			try {
				$share = $this->collectiveShareMapper->findOneByToken($this->getToken());
			} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
				throw new NotFoundException('Failed to get shared collective', 0, $e);
			}
			$collective = $this->service->getCollectiveWithShare($share->getCollectiveId(),
				$share->getOwner(),
				$share->getToken());
			// Explicitly set member level
			$collective->setLevel(Member::LEVEL_MEMBER);
			return $collective;
		}, $this->logger);
		return new DataResponse(['collectives' => [$collective]]);
	}
}
