<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\RecentPagesService;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class RecentPagesServiceTest extends TestCase {
	private CollectiveService $collectiveService;
	private RecentPagesService $service;
	private IUser $user;

	protected function setUp(): void {
		$this->collectiveService = $this->createMock(CollectiveService::class);
		$dbc = $this->createMock(IDBConnection::class);
		$config = $this->createMock(IConfig::class);
		$mimeTypeLoader = $this->createMock(IMimeTypeLoader::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$l10n = $this->createMock(IL10N::class);
		$rootFolder = $this->createMock(IRootFolder::class);

		$this->service = new RecentPagesService(
			$this->collectiveService,
			$dbc,
			$config,
			$mimeTypeLoader,
			$urlGenerator,
			$l10n,
			$rootFolder
		);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('user');
	}

	public function testForUserGetCollectivesException(): void {
		$this->collectiveService->method('getCollectives')->willThrowException(new NotFoundException(''));

		$recentPages = $this->service->forUser($this->user);
		self::assertEquals($recentPages, []);
	}

	public function testForUserEmptyCollectives(): void {
		$this->collectiveService->method('getCollectives')->willReturn([]);

		$recentPages = $this->service->forUser($this->user);
		self::assertEquals($recentPages, []);
	}
}
