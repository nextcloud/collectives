<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\RecentPagesService;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Cache\ICache;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class TeamRecentPagesServiceTest extends TestCase {
	private function service(CollectiveService $collectives, IDBConnection $database): RecentPagesService {
		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValueString')->with('instanceid', '')->willReturn('test');
		$cache = $this->createMock(ICache::class);
		$cache->method('getNumericStorageId')->willReturn(1);
		$storage = $this->createMock(IStorage::class);
		$storage->method('getCache')->willReturn($cache);
		$folder = $this->createMock(Folder::class);
		$folder->method('getStorage')->willReturn($storage);
		$root = $this->createMock(IRootFolder::class);
		$root->method('get')->willReturn($folder);
		return new RecentPagesService(
			$collectives,
			$this->createMock(PageService::class),
			$database,
			$config,
			$this->createMock(IMimeTypeLoader::class),
			$this->createMock(IURLGenerator::class),
			$this->createMock(IL10N::class),
			$root,
		);
	}

	private function collective(int $id, string $teamId): Collective {
		$collective = new Collective();
		$collective->setId($id);
		$collective->setCircleId($teamId);
		$collective->setName('Same name');
		return $collective;
	}

	public function testFiltersByTeamIdBeforeApplyingLimit(): void {
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->expects(self::once())->method('getCollectives')->with('alice')->willReturn([
			$this->collective(1, 'team-a'),
			$this->collective(2, 'team-b'),
		]);
		$database = $this->createMock(IDBConnection::class);
		$query = $this->createMock(IQueryBuilder::class);
		$database->method('getQueryBuilder')->willReturn($query);
		foreach (['select', 'from', 'innerJoin', 'where', 'andWhere', 'orderBy'] as $method) {
			$query->method($method)->willReturnSelf();
		}
		$parameters = [];
		$query->method('createNamedParameter')->willReturnCallback(static function ($value) use (&$parameters): string {
			$parameters[] = $value;
			return ':value';
		});
		$query->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$query->expects(self::once())->method('setMaxResults')->with(3)->willReturnSelf();
		$result = $this->createMock(IResult::class);
		$result->method('fetchAssociative')->willReturn(false);
		$query->method('executeQuery')->willReturn($result);
		self::assertSame([], $this->service($collectives, $database)->forTeam('team-a', 'alice', 3));
		self::assertContains('appdata_test/collectives/1/%', $parameters);
		self::assertNotContains('appdata_test/collectives/2/%', $parameters);
	}

	public function testUnrelatedCollectiveIsNotApplicable(): void {
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->method('getCollectives')->willReturn([$this->collective(2, 'team-b')]);
		$database = $this->createMock(IDBConnection::class);
		$database->expects(self::never())->method('getQueryBuilder');
		self::assertNull($this->service($collectives, $database)->forTeam('team-a', 'alice'));
	}

	public function testTrashedCollectiveIsNotApplicable(): void {
		$collective = $this->collective(1, 'team-a');
		$collective->setTrashTimestamp(123);
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->method('getCollectives')->willReturn([$collective]);
		$database = $this->createMock(IDBConnection::class);
		$database->expects(self::never())->method('getQueryBuilder');
		self::assertNull($this->service($collectives, $database)->forTeam('team-a', 'alice'));
	}

	public function testDeniedCollectiveIsNotApplicable(): void {
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->method('getCollectives')->willThrowException(new NotPermittedException('Denied'));
		$database = $this->createMock(IDBConnection::class);
		$database->expects(self::never())->method('getQueryBuilder');
		self::assertNull($this->service($collectives, $database)->forTeam('team-a', 'alice'));
	}
}
