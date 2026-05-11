<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * Mostly copied from server/lib/public/AppFramework/PublicShareController.php
 */
abstract class CollectivesPublicOCSController extends OCSController {
	private string $token;

	public function __construct(
		string $appName,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Middleware set the token for the request
	 */
	final public function setToken(string $token): void {
		$this->token = $token;
	}

	/**
	 * Get the token for this request
	 */
	final public function getToken(): string {
		return $this->token;
	}

	/**
	 * Is the provided token a valid token
	 *
	 * This function is already called from the middleware directly after setting the token.
	 */
	abstract public function isValidToken(): bool;

	/**
	 * Function called if the share is not found.
	 *
	 * You can use this to do some logging for example
	 */
	public function shareNotFound(): void {
	}
}
