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
use OCP\AppFramework\Db\DoesNotExistException;
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

	private function makeProvidedStaticSite(): StaticSite {
		$staticSite = $this->makeStaticSite();
		$staticSite->setStatus(StaticSite::STATUS_PROVIDED);
		$staticSite->setPublishedAt(1600000000);
		$staticSite->resetUpdatedFields();
		return $staticSite;
	}

	public function testUpdateStatusIfUnchangedOnlyUpdatesUnchangedStaticSite(): void {
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->updateStatusIfUnchanged($this->makeStaticSite(), StaticSite::STATUS_PENDING));

		$this->assertSame([
			'id = 7',
			"status = 'published'",
			'updated_at = 1700000000',
		], $this->conditions);
		$this->assertSame([
			'status' => StaticSite::STATUS_PENDING,
			'last_error' => null,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testUpdateStatusIfUnchangedReturnsFalseIfStaticSiteChangedMeanwhile(): void {
		$this->qb->method('executeStatement')->willReturn(0);

		$this->assertFalse($this->mapper->updateStatusIfUnchanged($this->makeStaticSite(), StaticSite::STATUS_PENDING));
	}

	public function testRestoreStatusKeepsPreviousUpdateTime(): void {
		$this->qb->method('executeStatement')->willReturn(1);
		$current = new StaticSite();
		$current->setId(7);
		$current->setStatus(StaticSite::STATUS_PENDING);
		$current->setUpdatedAt($this->now);
		$current->resetUpdatedFields();
		$this->mapper->expects($this->once())->method('find')->with(7)->willReturn($current);

		$this->mapper->restoreStatus($this->makeStaticSite());

		$this->assertSame(['id = 7'], $this->conditions);
		$this->assertSame([
			'status' => StaticSite::STATUS_PUBLISHED,
			'updated_at' => 1700000000,
		], $this->values);
	}

	public function testRestoreStatusRestoresLastError(): void {
		$this->qb->method('executeStatement')->willReturn(1);
		$current = new StaticSite();
		$current->setId(7);
		$current->setStatus(StaticSite::STATUS_PENDING);
		$current->setUpdatedAt($this->now);
		$current->resetUpdatedFields();
		$this->mapper->method('find')->willReturn($current);
		$previous = $this->makeStaticSite();
		$previous->setStatus(StaticSite::STATUS_FAILED);
		$previous->setLastError(StaticSite::ERROR_GENERATOR_FAILED);

		$this->mapper->restoreStatus($previous);

		$this->assertSame([
			'status' => StaticSite::STATUS_FAILED,
			'last_error' => StaticSite::ERROR_GENERATOR_FAILED,
			'updated_at' => 1700000000,
		], $this->values);
	}

	public function testRestoreStatusThrowsIfStaticSiteWasDeleted(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(NotFoundException::class);
		$this->mapper->restoreStatus($this->makeStaticSite());
	}

	public function testUpdateSettingsAndStatusStoresTitlePagesAndStatus(): void {
		$this->qb->method('executeStatement')->willReturn(1);
		$provided = new StaticSite();
		$this->mapper->expects($this->once())->method('find')->with(7)->willReturn($provided);

		$this->assertSame($provided, $this->mapper->updateSettingsAndStatus($this->makeStaticSite(), 'Title', [3, 1], StaticSite::STATUS_FAILED));

		$this->assertSame(['id = 7'], $this->conditions);
		$this->assertSame([
			'title' => 'Title',
			'selected_pages' => '[3,1]',
			'status' => StaticSite::STATUS_FAILED,
			'last_error' => null,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testUpdateSettingsAndStatusStoresLastError(): void {
		$this->qb->method('executeStatement')->willReturn(1);
		$this->mapper->method('find')->willReturn(new StaticSite());

		$this->mapper->updateSettingsAndStatus($this->makeStaticSite(), 'Title', [1], StaticSite::STATUS_FAILED, StaticSite::ERROR_GENERATOR_REJECTED);

		$this->assertSame(StaticSite::ERROR_GENERATOR_REJECTED, $this->values['last_error']);
	}

	public function testUpdateSettingsAndStatusThrowsIfStaticSiteWasDeleted(): void {
		$this->qb->method('executeStatement')->willReturn(0);
		$this->mapper->expects($this->never())->method('find');

		$this->expectException(NotFoundException::class);
		$this->mapper->updateSettingsAndStatus($this->makeStaticSite(), 'Title', [1], StaticSite::STATUS_PROVIDED);
	}

	public function testUpdateStatusPublishedStoresUrlAndPublicationTimeInOneUpdate(): void {
		$this->mapper->method('find')->with(7)->willReturn($this->makeProvidedStaticSite());

		$staticSite = $this->mapper->updateStatusPublished(7, 'https://example.org/site');

		$this->assertSame(StaticSite::STATUS_PUBLISHED, $staticSite->getStatus());
		$this->assertSame('https://example.org/site', $staticSite->getPublishedUrl());
		$this->assertSame($this->now, $staticSite->getPublishedAt());
		$this->assertNull($staticSite->getLastError());
		$this->assertSame([
			'status' => StaticSite::STATUS_PUBLISHED,
			'published_url' => 'https://example.org/site',
			'published_at' => $this->now,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testUpdateStatusFailedStoresLastErrorAndKeepsPreviousPublication(): void {
		$this->mapper->method('find')->with(7)->willReturn($this->makeProvidedStaticSite());

		$staticSite = $this->mapper->updateStatusFailed(7, StaticSite::ERROR_GENERATOR_FAILED);

		$this->assertSame(StaticSite::STATUS_FAILED, $staticSite->getStatus());
		$this->assertSame(StaticSite::ERROR_GENERATOR_FAILED, $staticSite->getLastError());
		$this->assertSame(1600000000, $staticSite->getPublishedAt());
		$this->assertSame([
			'status' => StaticSite::STATUS_FAILED,
			'last_error' => StaticSite::ERROR_GENERATOR_FAILED,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testUpdateStatusPublishedThrowsIfStaticSiteWasDeleted(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(NotFoundException::class);
		$this->mapper->updateStatusPublished(7, 'https://example.org/site');
	}

	public function testUpdateStatusFailedThrowsIfStaticSiteWasDeleted(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(NotFoundException::class);
		$this->mapper->updateStatusFailed(7, StaticSite::ERROR_GENERATOR_FAILED);
	}
}
