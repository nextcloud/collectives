<?php

namespace Unit\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCA\Collectives\Service\CollectiveUserSettingsService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use PHPUnit\Framework\TestCase;

class CollectiveUserSettingsServiceTest extends TestCase {
	private $collectiveUserSettingsMapper;
	private $collectiveMapper;
	private $service;

	protected function setUp(): void {
		$this->collectiveUserSettingsMapper = $this->getMockBuilder(CollectiveUserSettingsMapper::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectiveMapper = $this->getMockBuilder(CollectiveMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->service = new CollectiveUserSettingsService(
			$this->collectiveUserSettingsMapper,
			$this->collectiveMapper
		);
	}

	public function testSetPageOrderNoCollective(): void {
		$this->collectiveMapper->method('findByIdAndUser')
			->willReturn(null);

		$this->expectException(NotFoundException::class);
		$this->service->setPageOrder(1, 'user', Collective::defaultPageOrder);
	}

	public function testSetPageOrderInvalidPageOrder(): void {
		$this->collectiveMapper->method('findByIdAndUser')
			->willReturn(new Collective());
		$this->collectiveUserSettingsMapper->method('findByCollectiveAndUser')
			->willReturn(null);

		$this->collectiveUserSettingsMapper->expects(self::never())
			->method('insertOrUpdate');
		$this->expectException(NotPermittedException::class);
		$this->service->setPageOrder(1, 'user', min(array_keys(Collective::pageOrders)) - 1);
	}

	public function testSetPageOrderRuntimeException(): void {
		$this->collectiveMapper->method('findByIdAndUser')
			->willReturn(new Collective());
		$this->collectiveUserSettingsMapper->method('findByCollectiveAndUser')
			->willReturn(null);

		$this->collectiveUserSettingsMapper->expects(self::once())
			->method('insertOrUpdate')
			->willThrowException(new \RuntimeException());
		$this->expectException(NotPermittedException::class);
		$this->service->setPageOrder(1, 'user', Collective::defaultPageOrder);
	}
}
