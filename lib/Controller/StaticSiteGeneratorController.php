<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\StaticSiteGeneratorClient;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * Provides admins access to the external static site generator.
 */
class StaticSiteGeneratorController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly StaticSiteGeneratorClient $client,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Check whether the configured static site generator is reachable and accepts the token
	 *
	 * This endpoint requires admin access
	 *
	 * @return DataResponse<Http::STATUS_OK, array{status: 'ok'}, array{}>|DataResponse<Http::STATUS_BAD_GATEWAY, array{message: string}, array{}>
	 *
	 * 200: Static site generator is healthy
	 * 502: Static site generator is not configured, not reachable or not healthy
	 */
	// TODO(Publish): Change when ext service provides a test endpoint to validate url and shared secret
	public function health(): DataResponse {
		try {
			$this->client->checkHealth();
		} catch (StaticSiteGeneratorException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_BAD_GATEWAY);
		}
		return new DataResponse(['status' => 'ok']);
	}
}
