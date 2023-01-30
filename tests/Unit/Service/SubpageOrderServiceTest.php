<?php

namespace Unit\Service;

use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\SubpageOrderService;
use PHPUnit\Framework\TestCase;

class SubpageOrderServiceTest extends TestCase {
	public function testVerify(): void {
		// valid
		SubpageOrderService::verify(null);
		SubpageOrderService::verify('[]');
		SubpageOrderService::verify('[1]');
		SubpageOrderService::verify('[1, 2]');
		SubpageOrderService::verify('[1,2,9999]');

		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('Invalid format of subpage order');

		SubpageOrderService::verify(1);
	}

	public function testVerifyInvalid(): void {
		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('Invalid format of subpage order');

		SubpageOrderService::verify('string');
	}

	public function testVerifyInvalid2(): void {
		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('Invalid format of subpage order');

		SubpageOrderService::verify('[string]');
	}

	public function testVerifyInvalid3(): void {
		$this->expectException(NotPermittedException::class);
		$this->expectExceptionMessage('Invalid format of subpage order');

		SubpageOrderService::verify('{a: b}');
	}

	public function testAdd(): void {
		$subpageOrder = '[1,2,3]';
		self::assertEquals('[0,1,2,3]', SubpageOrderService::add($subpageOrder, 0));
		self::assertEquals('[1,2,3,4]', SubpageOrderService::add($subpageOrder, 4, 3));
		self::assertEquals('[2,1,3]', SubpageOrderService::add($subpageOrder, 2));
	}

	public function testRemove(): void {
		$subpageOrder = '[1,2,3]';
		self::assertEquals('[1,2,3]', SubpageOrderService::remove($subpageOrder, 7));
		self::assertEquals('[1,3]', SubpageOrderService::remove($subpageOrder, 2));
	}
}
