<?php

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Service\AlreadyExistsException;
use OCA\Collectives\Service\NotFoundException;

use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use Psr\Log\LoggerInterface;

trait ErrorHelper {
	/**
	 * @param Closure              $callback
	 * @param LoggerInterface|null $logger
	 *
	 * @return DataResponse
	 */
	protected function handleErrorResponse(Closure $callback, ?LoggerInterface $logger): DataResponse {
		try {
			return new DataResponse($callback());
		} catch (NotPermittedException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_FORBIDDEN);
		} catch (NotFoundException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		} catch (AlreadyExistsException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (\Throwable $e) {
			if ($logger) {
				$logger->error('Collectives App Error: ' . $e->getMessage(), ['exception' => $e]);
			}
			return new DataResponse('Internal Server Error', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
