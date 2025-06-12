<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use Psr\Log\LoggerInterface;

trait OCSExceptionHelper {
	/**
	 * @throws OCSForbiddenException
	 * @throws OCSNotFoundException
	 */
	protected function handleErrorResponse(Closure $callback, ?LoggerInterface $logger): mixed {
		try {
			return $callback();
		} catch (NotPermittedException $e) {
			$logger?->debug('Collectives app NotPermitted Error: ' . $e->getMessage(), ['exception' => $e]);
			throw new OCSForbiddenException($e->getMessage());
		} catch (NotFoundException $e) {
			$logger?->debug('Collectives app NotFound Error: ' . $e->getMessage(), ['exception' => $e]);
			throw new OCSNotFoundException($e->getMessage());
		}
	}
}
