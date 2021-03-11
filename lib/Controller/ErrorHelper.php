<?php

namespace OCA\Collectives\Controller;

use Closure;

use OCA\Collectives\Service\AlreadyExistsException;
use OCA\Collectives\Service\NotFoundException;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;

trait ErrorHelper {
	/**
	 * @param Closure $callback
	 *
	 * @return DataResponse
	 */
	protected function handleErrorResponse(Closure $callback): DataResponse {
		try {
			return new DataResponse($callback());
		} catch (NotFoundException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_NOT_FOUND);
		} catch (AlreadyExistsException $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_UNPROCESSABLE_ENTITY);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
