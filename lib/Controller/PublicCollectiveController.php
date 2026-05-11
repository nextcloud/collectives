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
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to public collective and page shares.
 *
 * @psalm-import-type CollectivesCollective from ResponseDefinitions
 */
class PublicCollectiveController extends CollectivesPublicOCSController {
	use OCSExceptionHelper;

	public function __construct(
		string $AppName,
		IRequest $request,
		private CollectiveShareMapper $collectiveShareMapper,
		private CollectiveService $service,
		private LoggerInterface $logger,
	) {
		parent::__construct($AppName, $request);
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
	 * Get public collective/page share by token
	 *
	 * @return DataResponse<Http::STATUS_OK, array{collectives: list<CollectivesCollective>}, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Public collective/page share not found
	 *
	 * 200: Public collective/page share returned
	 */
	#[PublicPage]
	#[AnonRateLimit(limit: 10, period: 60)]
	public function get(): DataResponse {
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
