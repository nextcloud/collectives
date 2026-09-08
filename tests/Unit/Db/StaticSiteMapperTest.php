<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Db;

use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Db\StaticSiteMapper;
use OCA\Collectives\Service\NotFoundException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * Records the conditions and values of update queries, as unit tests can't use the database.
 */
class StaticSiteMapperTest extends TestCase {
	private IQueryBuilder&MockObject $qb;
	private StaticSiteMapper&MockObject $mapper;

	private int $now = 1800000000;
	/** @var array<string, mixed> */
	private array $parameters = [];
	/** @var list<string> */
	private array $conditions = [];
	/** @var array<string, mixed> */
	private array $values = [];

	protected function setUp(): void {
		parent::setUp();

		$this->qb = $this->createMock(IQueryBuilder::class);
		$this->qb->method('createNamedParameter')->willReturnCallback(function (mixed $value): string {
			$name = ':param' . count($this->parameters);
			$this->parameters[$name] = $value;
			return $name;
		});
		$expr = $this->createMock(IExpressionBuilder::class);
		$expr->method('eq')->willReturnCallback(
			fn (string $column, string $parameter): string => $column . ' = ' . var_export($this->parameters[$parameter], true)
		);
		$this->qb->method('expr')->willReturn($expr);
		$this->qb->method('update')->willReturnSelf();
		$this->qb->method('set')->willReturnCallback(function (string $column, string $parameter): IQueryBuilder {
			$this->values[$column] = $this->parameters[$parameter];
			return $this->qb;
		});
		$this->qb->method('where')->willReturnCallback(function (string $condition): IQueryBuilder {
			$this->conditions[] = $condition;
			return $this->qb;
		});
		$this->qb->method('andWhere')->willReturnCallback(function (string $condition): IQueryBuilder {
			$this->conditions[] = $condition;
			return $this->qb;
		});

		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getQueryBuilder')->willReturn($this->qb);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn($this->now);

		$this->mapper = $this->getMockBuilder(StaticSiteMapper::class)
			->setConstructorArgs([$connection, $timeFactory])
			->onlyMethods(['find'])
			->getMock();
	}

	private function makeStaticSite(): StaticSite {
		$staticSite = new StaticSite();
		$staticSite->setId(7);
		$staticSite->setStatus(StaticSite::STATUS_PUBLISHED);
		$staticSite->setUpdatedAt(1700000000);
		return $staticSite;
	}

	public function testStartPublicationOnlyUpdatesUnchangedStaticSite(): void {
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->startPublication($this->makeStaticSite()));

		$this->assertSame([
			'id = 7',
			"status = 'published'",
			'updated_at = 1700000000',
		], $this->conditions);
		$this->assertSame([
			'status' => StaticSite::STATUS_PENDING,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testStartPublicationReturnsFalseIfStaticSiteChangedMeanwhile(): void {
		$this->qb->method('executeStatement')->willReturn(0);

		$this->assertFalse($this->mapper->startPublication($this->makeStaticSite()));
	}

	public function testFinishPublicationStoresTitleAndPages(): void {
		$this->qb->method('executeStatement')->willReturn(1);
		$provided = new StaticSite();
		$this->mapper->expects($this->once())->method('find')->with(7)->willReturn($provided);

		$this->assertSame($provided, $this->mapper->finishPublication($this->makeStaticSite(), 'Title', [3, 1]));

		$this->assertSame(['id = 7'], $this->conditions);
		$this->assertSame([
			'title' => 'Title',
			'selected_pages' => '[3,1]',
			'status' => StaticSite::STATUS_PROVIDED,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testFinishPublicationThrowsIfStaticSiteWasDeleted(): void {
		$this->qb->method('executeStatement')->willReturn(0);
		$this->mapper->expects($this->never())->method('find');

		$this->expectException(NotFoundException::class);
		$this->mapper->finishPublication($this->makeStaticSite(), 'Title', [1]);
	}
}
