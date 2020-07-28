<?php

namespace OCA\Wiki\Controller;

use Closure;

use OCA\Wiki\Service\NotFoundException;

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
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}
}
