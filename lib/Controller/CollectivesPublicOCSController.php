<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\ISession;

/**
 * Mostly copied from server/lib/public/AppFramework/PublicShareController.php
 */
abstract class CollectivesPublicOCSController extends OCSController {
	private string $token;

	public function __construct(
		string $AppName,
		IRequest $request,
		private ISession $session,
	) {
		parent::__construct($AppName, $request);
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
	 * Get a hash of the password for this share
	 *
	 * To ensure access is blocked when the password to a share is changed we store
	 * a hash of the password for this token.
	 */
	abstract protected function getPasswordHash(): ?string;

	/**
	 * Is the provided token a valid token
	 *
	 * This function is already called from the middleware directly after setting the token.
	 */
	abstract public function isValidToken(): bool;

	/**
	 * Is a share with this token password protected
	 */
	abstract protected function isPasswordProtected(): bool;

	/**
	 * Check if a share is authenticated or not
	 */
	public function isAuthenticated(): bool {
		// Always authenticated against non password protected shares
		if (!$this->isPasswordProtected()) {
			return true;
		}

		// If we are authenticated properly
		if ($this->session->get('public_link_authenticated_token') === $this->getToken()
			&& $this->session->get('public_link_authenticated_password_hash') === $this->getPasswordHash()) {
			return true;
		}

		// Fail by default if nothing matches
		return false;
	}

	/**
	 * Function called if the share is not found.
	 *
	 * You can use this to do some logging for example
	 */
	public function shareNotFound(): void {
	}
}
