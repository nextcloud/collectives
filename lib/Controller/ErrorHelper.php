<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use Closure;
use InvalidArgumentException;
use OCA\Collectives\Service\CircleExistsException;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\QueryException;
use Psr\Log\LoggerInterface;
use Throwable;

trait ErrorHelper {
	protected function handleErrorResponse(Closure $callback, ?LoggerInterface $logger): DataResponse {
		try {
			return new DataResponse($callback());
		} catch (NotPermittedException $e) {
			$logger?->debug('Collectives App NotPermitted Error: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse($e->getMessage(), Http::STATUS_FORBIDDEN);
		} catch (NotFoundException $e) {
			$logger?->debug('Collectives App NotFound Error: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		} catch (CircleExistsException | QueryException | UnprocessableEntityException $e) {
			$logger?->debug('Collectives App CircleExists Error: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse($e->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (InvalidArgumentException $e) {
			$logger?->debug('Collectives App InvalidArgument Error: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		} catch (Throwable $e) {
			$logger?->error('Collectives App Error: ' . $e->getMessage(), ['exception' => $e]);
			return new DataResponse('Internal Server Error', Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
