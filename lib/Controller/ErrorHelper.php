<?php

namespace OCA\Unite\Controller;

use Closure;

use OCA\Unite\Service\NotFoundException;

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
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
