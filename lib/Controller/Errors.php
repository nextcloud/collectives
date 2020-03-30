<?php

namespace OCA\Wiki\Controller;

use Closure;

use OCP\AppFramework\Http;
Use OCP\AppFramework\Http\DataResponse;

use OCA\Wiki\Service\NotFoundException;

trait Errors {
	/**
	 * @param Closure $callback
	 *
	 * @return DataResponse
	 */
	protected function handleNotFound(Closure $callback): DataResponse {
		try {
			return new DataResponse($callback());
		} catch(NotFoundException $e) {
			$message = ['message' => $e->getMessage()];
			return new DataResponse($message, Http::STATUS_NOT_FOUND);
		}
	}
}
