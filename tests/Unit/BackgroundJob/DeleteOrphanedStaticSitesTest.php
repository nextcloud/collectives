<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\BackgroundJob;

use OCA\Collectives\BackgroundJob\DeleteOrphanedStaticSites;
use OCA\Collectives\Service\StaticSiteRemover;
use OCP\AppFramework\Utility\ITimeFactory;
use ReflectionMethod;
use Test\TestCase;

class DeleteOrphanedStaticSitesTest extends TestCase {
	public function testRunRetriesDeletingWithCurrentTime(): void {
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1800000000);
		$remover = $this->createMock(StaticSiteRemover::class);
		$remover->expects($this->once())
			->method('removeAllOrphaned')
			->with(1800000000);

		$job = new DeleteOrphanedStaticSites($timeFactory, $remover);
		(new ReflectionMethod($job, 'run'))->invoke($job, null);
	}
}
