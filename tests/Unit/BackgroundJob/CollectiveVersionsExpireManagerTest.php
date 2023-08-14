<?php
/**
 * @copyright Copyright (c) 2023 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace BackgroundJob;

use OC\Files\FileInfo;
use OC\User\User;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Versions\CollectiveVersionsExpireManager;
use OCA\Collectives\Versions\ExpireManager;
use OCA\Collectives\Versions\VersionsBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Test\TestCase;

class CollectiveVersionsExpireManagerTest extends TestCase {
	private VersionsBackend|MockObject $versionsBackend;

	public function setUp(): void {
		$this->versionsBackend = $this->createMock(VersionsBackend::class);
	}

	public function testEventDispatcherFallback() {
		$appContainer = $this->createMock(ContainerInterface::class);
		$appContainer->method('get')
			->with(VersionsBackend::class)
			->willReturn($this->versionsBackend);

		$file = $this->createMock(FileInfo::class);
		$this->versionsBackend->method('getAllVersionedFiles')
			->willReturn([$file]);
		$this->versionsBackend->expects($this->once())
			->method('getVersionsForFile')
			->willReturnCallback(function ($user, $passedFile) use ($file) {
				self::assertEquals($file, $passedFile);
				self::assertInstanceOf(User::class, $user);
				return [];
			});

		$manager = new CollectiveVersionsExpireManager(
			$appContainer,
			$this->createMock(CollectiveFolderManager::class),
			$this->createMock(ExpireManager::class),
			$this->createMock(IDBConnection::class),
			$this->createMock(CollectiveMapper::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(IEventDispatcher::class),
		);
		$manager->expireFolder(['id' => 123]);
	}
}
