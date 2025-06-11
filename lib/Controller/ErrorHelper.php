<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use Closure;
use InvalidArgumentException;
use OCA\Collectives\Service\CircleExistsException;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCS\OCSPreconditionFailedException;
use OCP\AppFramework\QueryException;
use Psr\Log\LoggerInterface;
use Throwable;

trait ErrorHelper {
	/**
	 * @return mixed
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
		// } catch (CircleExistsException|UnprocessableEntityException $e) {
		// 	$logger?->debug('Collectives app CircleExists Error: ' . $e->getMessage(), ['exception' => $e]);
		// 	throw new OCSPreconditionFailedException($e->getMessage());
		// } catch (InvalidArgumentException $e) {
		// 	$logger?->debug('Collectives app InvalidArgument Error: ' . $e->getMessage(), ['exception' => $e]);
		// 	throw new OCSBadRequestException($e->getMessage());
		// } catch (Throwable $e) {
		// 	$logger?->error('Collectives app Error: ' . $e->getMessage(), ['exception' => $e]);
		// 	return new DataResponse(['error' => 'Internal Server Error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
