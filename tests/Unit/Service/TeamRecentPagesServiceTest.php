<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
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
use OCP\IUser;
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
		$root->method('get')->with('appdata_test')->willReturn($folder);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRoute')->willReturnCallback(static fn (string $route, array $parameters): string => '/collectives/' . $parameters['path']);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		return new RecentPagesService(
			$collectives,
			$this->createMock(PageService::class),
			$database,
			$config,
			$this->createMock(IMimeTypeLoader::class),
			$urls,
			$l10n,
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
		foreach (['select', 'from', 'innerJoin', 'where', 'andWhere'] as $method) {
			$query->method($method)->willReturnSelf();
		}
		$parameters = [];
		$query->method('createNamedParameter')->willReturnCallback(static function ($value) use (&$parameters): string {
			$parameters[] = $value;
			return ':value';
		});
		$query->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$query->expects(self::once())->method('orderBy')->with('f.mtime', 'DESC')->willReturnSelf();
		$query->expects(self::once())->method('setMaxResults')->with(3)->willReturnSelf();
		$result = $this->createMock(IResult::class);
		$result->method('fetchAssociative')->willReturnOnConsecutiveCalls(
			['path' => 'appdata_test/collectives/1/Guides/Handbook.md', 'filename' => 'Handbook.md', 'file_id' => 42, 'slug' => 'handbook', 'timestamp' => 200, 'emoji' => 'A'],
			['path' => 'appdata_test/collectives/1/Readme.md', 'filename' => 'Readme.md', 'file_id' => 43, 'slug' => null, 'timestamp' => 100, 'emoji' => null],
			['path' => 'appdata_test/collectives/2/Private.md', 'filename' => 'Private.md', 'file_id' => 44, 'slug' => null, 'timestamp' => 50, 'emoji' => null],
			false,
		);
		$result->expects(self::once())->method('closeCursor');
		$query->expects(self::once())->method('executeQuery')->willReturn($result);

		$pages = $this->service($collectives, $database)->forTeam('team-a', 'alice', 3);

		self::assertCount(2, $pages);
		self::assertSame('Handbook', $pages[0]->getTitle());
		self::assertSame('Guides', $pages[0]->getPagePath());
		self::assertSame('/collectives/Same name/handbook-42', $pages[0]->getPageUrl());
		self::assertSame(200, $pages[0]->getTimestamp());
		self::assertSame('A', $pages[0]->getEmoji());
		self::assertSame('Landing page', $pages[1]->getTitle());
		self::assertSame('/collectives/Same name?fileId=43', $pages[1]->getPageUrl());
		self::assertContains('appdata_test/collectives/1/%', $parameters);
		self::assertNotContains('appdata_test/collectives/2/%', $parameters);
	}

	public function testTeamCollectivesAreFilteredAndReindexed(): void {
		$matching = $this->collective(2, 'team-a');
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->expects(self::once())->method('getCollectives')->with('alice')->willReturn([
			$this->collective(1, 'team-b'), $matching,
		]);
		$database = $this->createMock(IDBConnection::class);
		$database->expects(self::never())->method('getQueryBuilder');

		self::assertSame([$matching], $this->service($collectives, $database)->getTeamCollectives('team-a', 'alice'));
	}

	public function testUnrelatedCollectiveReturnsEmptyListWithoutQuery(): void {
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->method('getCollectives')->willReturn([$this->collective(2, 'team-b')]);
		$database = $this->createMock(IDBConnection::class);
		$database->expects(self::never())->method('getQueryBuilder');

		self::assertSame([], $this->service($collectives, $database)->forTeam('team-a', 'alice'));
	}

	public function testDeniedCollectiveReturnsEmptyListWithoutQuery(): void {
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->method('getCollectives')->willThrowException(new NotPermittedException('Denied'));
		$database = $this->createMock(IDBConnection::class);
		$database->expects(self::never())->method('getQueryBuilder');

		self::assertSame([], $this->service($collectives, $database)->forTeam('team-a', 'alice'));
	}

	public function testMissingCollectiveReturnsEmptyListWithoutQuery(): void {
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->method('getCollectives')->willThrowException(new NotFoundException('Missing'));
		$database = $this->createMock(IDBConnection::class);
		$database->expects(self::never())->method('getQueryBuilder');

		self::assertSame([], $this->service($collectives, $database)->forTeam('team-a', 'alice'));
	}

	public function testPersonalQueryStillUsesUserAndReturnsEmptyList(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$collectives = $this->createMock(CollectiveService::class);
		$collectives->expects(self::once())->method('getCollectives')->with('alice')->willReturn([]);
		$database = $this->createMock(IDBConnection::class);
		$database->expects(self::never())->method('getQueryBuilder');

		self::assertSame([], $this->service($collectives, $database)->forUser($user));
	}
}
