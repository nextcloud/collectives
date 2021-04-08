<?php

namespace Unit\Service;

require_once __DIR__ . '/../../../lib/Service/ServiceException.php';
require_once __DIR__ . '/../../../lib/Service/ConflictException.php';

use JsonSerializable;
use PHPUnit\Framework\TestCase;
use OCA\Collectives\Service\ConflictException;

class ConflictExceptionTest extends TestCase {
	public function testMessage(): void {
		$dummy = new Dummy('test me');
		$message = "Dummy already exists";
		$exc = new ConflictException($message, $dummy);
		self::assertEquals($message, $exc->getMessage());
	}

	public function testJsonLoad(): void {
		$dummy = new Dummy('test me');
		$message = "Dummy already exists";
		$exc = new ConflictException($message, $dummy);
		self::assertEquals($dummy->jsonSerialize(), $exc->jsonSerialize());
	}
}

class Dummy implements JsonSerializable {
	private $name;

	public function __construct(
		String $name) {
		$this->name = $name;
	}

	public function jsonSerialize() {
		return [
			'name' => $this->name,
		];
	}
}
