<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\StaticSiteService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Renders static sites from collectives via the Hugo SSG.
 */
class StaticSiteController extends OCSController {
	use UserTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private StaticSiteService $service,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Render a sample static site with Hugo and store it in the user's files.
	 *
	 * @param string|null $title Optional title shown on the generated site
	 *
	 * @return DataResponse<Http::STATUS_OK, array{path: string}, array{}>
	 * @throws OCSException Build or storage failed
	 *
	 * 200: Static site generated and stored
	 */
	#[NoAdminRequired]
	public function create(?string $title = null): DataResponse {
		try {
			return new DataResponse($this->service->generateSampleSite($this->getUid(), $title));
		} catch (MissingDependencyException $e) {
			throw new OCSException($e->getMessage(), Http::STATUS_NOT_IMPLEMENTED, $e);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to generate static site', ['exception' => $e]);
			throw new OCSException($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR, $e);
		}
	}
}
