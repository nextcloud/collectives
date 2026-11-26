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
use OCP\DB\IResult;
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
			fn (string $column, string $parameter): string => $column . ' = ' . $this->describeOperand($parameter)
		);
		$expr->method('neq')->willReturnCallback(
			fn (string $column, string $parameter): string => $column . ' != ' . var_export($this->parameters[$parameter], true)
		);
		$expr->method('isNull')->willReturnCallback(fn (string $column): string => $column . ' IS NULL');
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

	/**
	 * Value of a named parameter, or the column it compares with
	 */
	private function describeOperand(string $operand): string {
		return array_key_exists($operand, $this->parameters) ? var_export($this->parameters[$operand], true) : $operand;
	}

	private function makeStaticSite(): StaticSite {
		$staticSite = new StaticSite();
		$staticSite->setId(7);
		$staticSite->setStatus(StaticSite::STATUS_PUBLISHED);
		$staticSite->setUpdatedAt(1700000000);
		return $staticSite;
	}

	private function makeBuildingStaticSite(string $status = StaticSite::STATUS_PROVIDED): StaticSite {
		$staticSite = $this->makeStaticSite();
		$staticSite->setStatus($status);
		$staticSite->setBuildId('current-build');
		$staticSite->setTitle('Title');
		$staticSite->setSelectedPages('[1,2]');
		$staticSite->setPublishedAt(1600000000);
		$staticSite->resetUpdatedFields();
		return $staticSite;
	}

	public function testStartBuildOnlyUpdatesUnchangedStaticSite(): void {
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->startBuild($this->makeStaticSite(), 'new-build', 'Title', StaticSite::THEME_TOPNAV, [3, 1]));

		$this->assertSame([
			'id = 7',
			"status = 'published'",
			'updated_at = 1700000000',
		], $this->conditions);
		$this->assertSame([
			'status' => StaticSite::STATUS_PENDING,
			'build_id' => 'new-build',
			'title' => 'Title',
			'theme' => StaticSite::THEME_TOPNAV,
			'selected_pages' => '[3,1]',
			'last_error' => null,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testStartBuildStoresNewSlug(): void {
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->startBuild($this->makeStaticSite(), 'new-build', 'Title', StaticSite::THEME_WIKI, [1], 'new-slug'));

		$this->assertSame('new-slug', $this->values['slug']);
	}

	public function testStartBuildReturnsFalseIfStaticSiteChangedMeanwhile(): void {
		$this->qb->method('executeStatement')->willReturn(0);

		$this->assertFalse($this->mapper->startBuild($this->makeStaticSite(), 'new-build', 'Title', StaticSite::THEME_WIKI, [1]));
	}

	public function testStartDeletionOnlyUpdatesUnchangedStaticSite(): void {
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->startDeletion($this->makeStaticSite(), 'delete-build'));

		$this->assertSame([
			'id = 7',
			"status = 'published'",
			'updated_at = 1700000000',
		], $this->conditions);
		$this->assertSame([
			'status' => StaticSite::STATUS_DELETING,
			'build_id' => 'delete-build',
			'last_error' => null,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testStartDeletionReturnsFalseIfStaticSiteChangedMeanwhile(): void {
		$this->qb->method('executeStatement')->willReturn(0);

		$this->assertFalse($this->mapper->startDeletion($this->makeStaticSite(), 'delete-build'));
	}

	public function testDeleteIfCurrentBuildOnlyDeletesCurrentBuild(): void {
		$this->qb->method('delete')->willReturnSelf();
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->deleteIfCurrentBuild($this->makeBuildingStaticSite(StaticSite::STATUS_DELETING)));

		$this->assertSame([
			'id = 7',
			"build_id = 'current-build'",
		], $this->conditions);
	}

	public function testDeleteIfCurrentBuildReturnsFalseIfAnotherRequestStarted(): void {
		$this->qb->method('delete')->willReturnSelf();
		$this->qb->method('executeStatement')->willReturn(0);

		$this->assertFalse($this->mapper->deleteIfCurrentBuild($this->makeBuildingStaticSite(StaticSite::STATUS_DELETING)));
	}

	public function testUpdateRequestStatusOnlyUpdatesPendingCurrentBuild(): void {
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->updateRequestStatus($this->makeBuildingStaticSite(StaticSite::STATUS_PENDING), StaticSite::STATUS_FAILED, StaticSite::ERROR_GENERATOR_REJECTED));

		$this->assertSame([
			'id = 7',
			"build_id = 'current-build'",
			"status = 'pending'",
		], $this->conditions);
		$this->assertSame([
			'status' => StaticSite::STATUS_FAILED,
			'last_error' => StaticSite::ERROR_GENERATOR_REJECTED,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testUpdateRequestStatusReturnsFalseIfCallbackConcludedBuildAlready(): void {
		$this->qb->method('executeStatement')->willReturn(0);

		$this->assertFalse($this->mapper->updateRequestStatus($this->makeBuildingStaticSite(StaticSite::STATUS_PENDING), StaticSite::STATUS_PROVIDED));
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

	public function testRestoreStatusRestoresSlug(): void {
		$this->qb->method('executeStatement')->willReturn(1);
		$current = new StaticSite();
		$current->setId(7);
		$current->setSlug('new-slug');
		$current->setStatus(StaticSite::STATUS_PENDING);
		$current->setUpdatedAt($this->now);
		$current->resetUpdatedFields();
		$this->mapper->method('find')->willReturn($current);
		$previous = $this->makeStaticSite();
		$previous->setSlug('old-slug');

		$this->mapper->restoreStatus($previous);

		$this->assertSame('old-slug', $this->values['slug']);
	}

	public function testRestoreStatusRestoresBuild(): void {
		$this->qb->method('executeStatement')->willReturn(1);
		$current = new StaticSite();
		$current->setId(7);
		$current->setBuildId('new-build');
		$current->setStatus(StaticSite::STATUS_PENDING);
		$current->setUpdatedAt($this->now);
		$current->resetUpdatedFields();
		$this->mapper->method('find')->willReturn($current);
		$previous = $this->makeStaticSite();
		$previous->setBuildId('old-build');

		$this->mapper->restoreStatus($previous);

		$this->assertSame('old-build', $this->values['build_id']);
	}

	public function testRestoreStatusRestoresSettings(): void {
		$this->qb->method('executeStatement')->willReturn(1);
		$current = new StaticSite();
		$current->setId(7);
		$current->setTitle('New Title');
		$current->setTheme(StaticSite::THEME_TOPNAV);
		$current->setSelectedPages('[2]');
		$current->setStatus(StaticSite::STATUS_PENDING);
		$current->setUpdatedAt($this->now);
		$current->resetUpdatedFields();
		$this->mapper->method('find')->willReturn($current);
		$previous = $this->makeStaticSite();
		$previous->setTitle('Old Title');
		$previous->setTheme(StaticSite::THEME_WIKI);
		$previous->setSelectedPages('[1]');

		$this->mapper->restoreStatus($previous);

		$this->assertSame('Old Title', $this->values['title']);
		$this->assertSame(StaticSite::THEME_WIKI, $this->values['theme']);
		$this->assertSame('[1]', $this->values['selected_pages']);
	}

	public function testRestoreStatusThrowsIfStaticSiteWasDeleted(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException('Not found'));

		$this->expectException(NotFoundException::class);
		$this->mapper->restoreStatus($this->makeStaticSite());
	}

	public static function unpublishedStatusProvider(): array {
		return [
			'pending' => [StaticSite::STATUS_PENDING],
			'provided' => [StaticSite::STATUS_PROVIDED],
			'failed' => [StaticSite::STATUS_FAILED],
		];
	}

	/**
	 * @dataProvider unpublishedStatusProvider
	 */
	public function testUpdateStatusPublishedStoresPublicationOfCurrentBuild(string $status): void {
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->updateStatusPublished($this->makeBuildingStaticSite($status), 'https://example.org/site'));

		$this->assertSame([
			'id = 7',
			"build_id = 'current-build'",
			"status != 'published'",
		], $this->conditions);
		$this->assertSame([
			'status' => StaticSite::STATUS_PUBLISHED,
			'published_build_id' => 'current-build',
			'published_title' => 'Title',
			'published_selected_pages' => '[1,2]',
			'published_url' => 'https://example.org/site',
			'published_at' => $this->now,
			'last_error' => null,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testUpdateStatusFailedStoresLastErrorAndKeepsPreviousPublication(): void {
		$this->qb->method('executeStatement')->willReturn(1);

		$this->assertTrue($this->mapper->updateStatusFailed($this->makeBuildingStaticSite(), StaticSite::ERROR_GENERATOR_FAILED));

		$this->assertSame([
			'id = 7',
			"build_id = 'current-build'",
			"status != 'published'",
		], $this->conditions);
		$this->assertSame([
			'status' => StaticSite::STATUS_FAILED,
			'last_error' => StaticSite::ERROR_GENERATOR_FAILED,
			'updated_at' => $this->now,
		], $this->values);
	}

	public function testUpdateStatusPublishedReturnsFalseIfBuildChangedOrGotPublished(): void {
		$this->qb->method('executeStatement')->willReturn(0);

		$this->assertFalse($this->mapper->updateStatusPublished($this->makeBuildingStaticSite(), 'https://example.org/site'));
	}

	public function testUpdateStatusFailedReturnsFalseIfBuildChangedOrGotPublished(): void {
		$this->qb->method('executeStatement')->willReturn(0);

		$this->assertFalse($this->mapper->updateStatusFailed($this->makeBuildingStaticSite(), StaticSite::ERROR_GENERATOR_FAILED));
	}

	public function testFindAllOrphanedFindsStaticSitesWithoutCollective(): void {
		$joins = [];
		$this->qb->method('select')->with('s.*')->willReturnSelf();
		$this->qb->method('from')->with('collectives_st_sites', 's')->willReturnSelf();
		$this->qb->method('leftJoin')->willReturnCallback(function (string $fromAlias, string $join, string $alias, string $condition) use (&$joins): IQueryBuilder {
			$joins[] = $fromAlias . ' LEFT JOIN ' . $join . ' ' . $alias . ' ON ' . $condition;
			return $this->qb;
		});
		$result = $this->createMock(IResult::class);
		$result->method('fetchAssociative')->willReturnOnConsecutiveCalls(['id' => 8, 'collective_id' => 3], false);
		$this->qb->method('executeQuery')->willReturn($result);

		$staticSites = $this->mapper->findAllOrphaned();

		$this->assertSame(['s LEFT JOIN collectives c ON s.collective_id = c.id'], $joins);
		$this->assertSame(['c.id IS NULL'], $this->conditions);
		$this->assertCount(1, $staticSites);
		$this->assertSame(8, $staticSites[0]->getId());
	}
}
