<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Middleware;

use OCA\Collectives\Controller\CollectivesPublicOCSController;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\Bruteforce\IThrottler;

/**
 * Mostly copied from server/lib/private/AppFramework/Middleware/PublicShare/PublicShareMiddleware.php
 */
class PublicOCSMiddleware extends Middleware {
	public function __construct(
		private IRequest $request,
		private IConfig $config,
		private IThrottler $throttler,
	) {
	}

	public function beforeController($controller, $methodName) {
		if (!($controller instanceof CollectivesPublicOCSController)) {
			return;
		}

		$controllerClassPath = explode('\\', $controller::class);
		$controllerShortClass = end($controllerClassPath);
		$bruteforceProtectionAction = $controllerShortClass . '::' . $methodName;
		$this->throttler->sleepDelayOrThrowOnMax($this->request->getRemoteAddress(), $bruteforceProtectionAction);

		if (!$this->isLinkSharingEnabled()) {
			throw new OCSNotFoundException('Link sharing is disabled');
		}

		// We require the token parameter to be set
		$token = $this->request->getParam('token');
		if ($token === null) {
			throw new OCSNotFoundException();
		}

		// Set the token
		$controller->setToken($token);

		if (!$controller->isValidToken()) {
			$this->throttle($bruteforceProtectionAction, $token);

			$controller->shareNotFound();
			throw new OCSNotFoundException();
		}

		// No need to check for authentication when we try to authenticate
		if ($methodName === 'authenticate' || $methodName === 'showAuthenticate') {
			return;
		}

		// If authentication succeeds just continue
		if ($controller->isAuthenticated()) {
			return;
		}

		$this->throttle($bruteforceProtectionAction, $token);
		throw new OCSNotFoundException();
	}

	/**
	 * Check if link sharing is allowed
	 */
	private function isLinkSharingEnabled(): bool {
		// Check if the shareAPI is enabled
		if ($this->config->getAppValue('core', 'shareapi_enabled', 'yes') !== 'yes') {
			return false;
		}

		// Check whether public sharing is enabled
		if ($this->config->getAppValue('core', 'shareapi_allow_links', 'yes') !== 'yes') {
			return false;
		}

		return true;
	}

	private function throttle($bruteforceProtectionAction, $token): void {
		$ip = $this->request->getRemoteAddress();
		$this->throttler->sleepDelayOrThrowOnMax($ip, $bruteforceProtectionAction);
		$this->throttler->registerAttempt($bruteforceProtectionAction, $ip, ['token' => $token]);
	}
}
