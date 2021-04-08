<?php

namespace OCA\Collectives\Service;

use JsonSerializable;

/*
 * The attempt to create a new record conflicts with an existing record.
 * Reveals the data of the existing record in the response.
 * Only use this if the user has access to the existing record.
 */
class ConflictException extends ServiceException implements JsonSerializable {
	private $existing;

	public function __construct(
		String $message,
		JsonSerializable $existing) {
		parent::__construct($message);
		$this->existing = $existing;
	}

	public function jsonSerialize() {
		return $this->existing->jsonSerialize();
	}
}
