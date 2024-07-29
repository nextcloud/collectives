<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Db;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveUserSettings;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class CollectiveUserSettingsMapperTest extends TestCase {
	private IQueryBuilder $qb;
	private CollectiveUserSettingsMapper $mapper;

	public function setUp(): void {
		$expr = $this->getMockBuilder(IExpressionBuilder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->qb = $this->getMockBuilder(IQueryBuilder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->qb->method('expr')
			->willReturn($expr);
		$connection = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();
		$connection->method('getQueryBuilder')
			->willReturn($this->qb);
		$this->mapper = new CollectiveUserSettingsMapper($connection);
	}

	public function testInsertOrUpdateInsert(): void {
		$settings = new CollectiveUserSettings();
		$settings->setCollectiveId(1);
		$settings->setUserId('user');
		$settings->setPageOrder(Collective::defaultPageOrder);

		$this->qb->expects(self::once())->method('insert');
		$this->qb->expects(self::never())->method('update');
		$this->mapper->insertOrUpdate($settings);
	}

	public function testInsertOrUpdateUpdate(): void {
		$settings = new CollectiveUserSettings();
		$settings->setId(1);
		$settings->setCollectiveId(1);
		$settings->setUserId('user');
		$settings->setPageOrder(Collective::defaultPageOrder);

		$this->qb->expects(self::never())->method('insert');
		$this->qb->expects(self::once())->method('update');
		$this->mapper->insertOrUpdate($settings);
	}
}
