<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Listeners\BeforeDirectFileDownloadListener;
use OCA\Collectives\Listeners\BeforeZipCreatedListener;
use OCA\Collectives\Service\DownloadCheckService;
use OCP\EventDispatcher\Event;
use OCP\Files\Events\BeforeDirectFileDownloadEvent;
use OCP\Files\Events\BeforeZipCreatedEvent;
use OCP\IUser;
use OCP\IUserSession;
use Test\TestCase;

class DownloadListenersTest extends TestCase {
	private IUserSession $userSession;

	protected function setUp(): void {
		parent::setUp();
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')->willReturn($user);
	}

	public function testDirectDownloadRemainsBlocked(): void {
		$check = $this->createMock(DownloadCheckService::class);
		$check->expects(self::once())->method('isDownloadBlocked')
			->with('alice', ['/.Collectives/Test/page.md'])->willReturn(true);
		$event = new BeforeDirectFileDownloadEvent('/.Collectives/Test/page.md');

		(new BeforeDirectFileDownloadListener($this->userSession, $check))->handle($event);

		self::assertFalse($event->isSuccessful());
		self::assertNotNull($event->getErrorMessage());
	}

	public function testZipDownloadRemainsBlocked(): void {
		$check = $this->createMock(DownloadCheckService::class);
		$check->expects(self::once())->method('isDownloadBlocked')
			->with('alice', ['/.Collectives/Test/page.md', '/.Collectives/Test/attachment.png'])->willReturn(true);
		$event = new BeforeZipCreatedEvent('/.Collectives/Test', ['page.md', 'attachment.png']);

		(new BeforeZipCreatedListener($this->userSession, $check))->handle($event);

		self::assertFalse($event->isSuccessful());
		self::assertNotNull($event->getErrorMessage());
	}

	public function testParentFolderZipRemainsBlocked(): void {
		$check = $this->createMock(DownloadCheckService::class);
		$check->expects(self::once())->method('isDownloadBlocked')->with('alice', ['/'])->willReturn(true);
		$event = new BeforeZipCreatedEvent('/', []);

		(new BeforeZipCreatedListener($this->userSession, $check))->handle($event);

		self::assertFalse($event->isSuccessful());
	}

	public function testAllowedDownloadsRemainAllowed(): void {
		$check = $this->createMock(DownloadCheckService::class);
		$check->method('isDownloadBlocked')->willReturn(false);
		$direct = new BeforeDirectFileDownloadEvent('/page.md');
		$zip = new BeforeZipCreatedEvent('/', ['page.md']);

		(new BeforeDirectFileDownloadListener($this->userSession, $check))->handle($direct);
		(new BeforeZipCreatedListener($this->userSession, $check))->handle($zip);

		self::assertTrue($direct->isSuccessful());
		self::assertTrue($zip->isSuccessful());
	}

	public function testUnrelatedEventsDoNotCheckDownloads(): void {
		$check = $this->createMock(DownloadCheckService::class);
		$check->expects(self::never())->method('isDownloadBlocked');

		(new BeforeDirectFileDownloadListener($this->userSession, $check))->handle(new Event());
		(new BeforeZipCreatedListener($this->userSession, $check))->handle(new Event());
	}
}
