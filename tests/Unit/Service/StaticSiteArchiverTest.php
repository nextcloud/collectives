<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Service\ServiceException;
use OCA\Collectives\Service\StaticSiteArchiver;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\Files\File;
use OCP\Files\GenericFileException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IL10N;
use OCP\ITempManager;
use PharData;
use PHPUnit\Framework\MockObject\MockObject;
use RecursiveIteratorIterator;
use Test\TestCase;

class StaticSiteArchiverTest extends TestCase {
	private IAppData&MockObject $appData;
	private ISimpleFolder&MockObject $folder;
	private ITempManager&MockObject $tempManager;
	private IL10N&MockObject $l10n;
	private StaticSiteArchiver $archiver;

	private string $staticSiteId = '01990000-0000-7000-8000-000000000000';
	private string $tempFolder;
	private ?string $storedArchive = null;

	protected function setUp(): void {
		parent::setUp();

		$this->tempFolder = sys_get_temp_dir() . '/collectives-archiver-test-' . bin2hex(random_bytes(4));
		mkdir($this->tempFolder);

		$this->appData = $this->createMock(IAppData::class);
		$this->folder = $this->createMock(ISimpleFolder::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->tempManager->method('getTemporaryFolder')->willReturn($this->tempFolder);

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(static fn (string $text, array $parameters = []): string => vsprintf($text, $parameters));

		$this->archiver = new StaticSiteArchiver($this->appData, $this->tempManager, $this->l10n);
	}

	protected function tearDown(): void {
		if (is_dir($this->tempFolder)) {
			exec('rm -rf ' . escapeshellarg($this->tempFolder));
		}
		parent::tearDown();
	}

	private function makeFile(string $content, string $path = '/collective/file'): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getPath')->willReturn($path);
		$file->method('fopen')->willReturnCallback(static function () use ($content) {
			$stream = fopen('php://memory', 'r+b');
			fwrite($stream, $content);
			rewind($stream);
			return $stream;
		});
		return $file;
	}

	private function captureContent(mixed $data): void {
		$this->storedArchive = is_resource($data) ? stream_get_contents($data) : $data;
	}

	/**
	 * @return array<string, string> Archive paths mapped to their contents
	 */
	private function readStoredArchive(): array {
		$this->assertNotNull($this->storedArchive);
		$path = sys_get_temp_dir() . '/collectives-archiver-read-' . bin2hex(random_bytes(4)) . '.tar.gz';
		file_put_contents($path, $this->storedArchive);
		try {
			$prefix = 'phar://' . $path . '/';
			$entries = [];
			foreach (new RecursiveIteratorIterator(new PharData($path)) as $entry) {
				$entries[substr($entry->getPathname(), strlen($prefix))] = file_get_contents($entry->getPathname());
			}
			ksort($entries);
			return $entries;
		} finally {
			unlink($path);
		}
	}

	public function testStoreCreatesArchiveWithFilesAtTheirPaths(): void {
		$this->appData->method('getFolder')->with('static_sites')->willReturn($this->folder);
		$this->folder->method('fileExists')->with($this->staticSiteId . '.tar.gz')->willReturn(false);
		$this->folder->expects($this->once())
			->method('newFile')
			->with($this->staticSiteId . '.tar.gz', $this->anything())
			->willReturnCallback(function (string $name, mixed $content) {
				$this->captureContent($content);
				return $this->createMock(ISimpleFile::class);
			});

		$this->archiver->store($this->staticSiteId, [
			'Readme.md' => $this->makeFile('# Home'),
			'.attachments.12/image.png' => $this->makeFile('png-data'),
			'Subpage/Readme.md' => $this->makeFile('# Sub'),
			'2024' => $this->makeFile('numeric'),
		]);

		$this->assertSame([
			'.attachments.12/image.png' => 'png-data',
			'2024' => 'numeric',
			'Readme.md' => '# Home',
			'Subpage/Readme.md' => '# Sub',
		], $this->readStoredArchive());
		$this->assertDirectoryDoesNotExist($this->tempFolder);
	}

	public function testStoreReplacesExistingArchive(): void {
		$existingFile = $this->createMock(ISimpleFile::class);
		$existingFile->expects($this->once())
			->method('putContent')
			->willReturnCallback(fn (mixed $content) => $this->captureContent($content));

		$this->appData->method('getFolder')->willReturn($this->folder);
		$this->folder->method('fileExists')->willReturn(true);
		$this->folder->method('getFile')->with($this->staticSiteId . '.tar.gz')->willReturn($existingFile);
		$this->folder->expects($this->never())->method('newFile');

		$this->archiver->store($this->staticSiteId, ['Readme.md' => $this->makeFile('# New')]);

		$this->assertSame(['Readme.md' => '# New'], $this->readStoredArchive());
	}

	public function testStoreWrapsStorageErrors(): void {
		$existingFile = $this->createMock(ISimpleFile::class);
		$existingFile->method('putContent')->willThrowException(new GenericFileException('Write failed'));

		$this->appData->method('getFolder')->willReturn($this->folder);
		$this->folder->method('fileExists')->willReturn(true);
		$this->folder->method('getFile')->willReturn($existingFile);

		$this->expectException(ServiceException::class);
		$this->archiver->store($this->staticSiteId, ['Readme.md' => $this->makeFile('# Home')]);
	}

	public function testStoreCreatesMissingAppDataFolder(): void {
		$this->appData->method('getFolder')->willThrowException(new FilesNotFoundException());
		$this->appData->expects($this->once())
			->method('newFolder')
			->with('static_sites')
			->willReturn($this->folder);
		$this->folder->method('fileExists')->willReturn(false);
		$this->folder->expects($this->once())->method('newFile');

		$this->archiver->store($this->staticSiteId, ['Readme.md' => $this->makeFile('# Home')]);
	}

	public static function invalidPathProvider(): array {
		return [
			'empty' => [''],
			'absolute' => ['/etc/passwd'],
			'parent segment' => ['../Readme.md'],
			'nested parent segment' => ['Subpage/../../Readme.md'],
			'current segment' => ['./Readme.md'],
			'empty segment' => ['Subpage//Readme.md'],
			'backslash' => ['Subpage\\Readme.md'],
		];
	}

	/**
	 * @dataProvider invalidPathProvider
	 */
	public function testStoreRejectsInvalidPaths(string $path): void {
		$this->appData->expects($this->never())->method('getFolder');
		$this->tempManager->expects($this->never())->method('getTemporaryFolder');

		$this->expectException(ServiceException::class);
		$this->archiver->store($this->staticSiteId, [$path => $this->makeFile('content')]);
	}

	public function testStoreSplitsLongPathIntoPrefixAndName(): void {
		$path = str_repeat('a', 150) . '/' . str_repeat('b', 90) . '.md';
		$this->appData->method('getFolder')->willReturn($this->folder);
		$this->folder->method('fileExists')->willReturn(false);
		$this->folder->method('newFile')->willReturnCallback(function (string $name, mixed $content) {
			$this->captureContent($content);
			return $this->createMock(ISimpleFile::class);
		});

		$this->archiver->store($this->staticSiteId, [$path => $this->makeFile('# Deep')]);

		$this->assertSame([$path => '# Deep'], $this->readStoredArchive());
	}

	public static function tooLongPathProvider(): array {
		return [
			'name without slash' => [str_repeat('a', 101)],
			'name after last slash' => ['Page/' . str_repeat('a', 101)],
			'prefix before slash' => [str_repeat('a', 160) . '/Readme.md'],
			'total length' => [str_repeat('a/', 128) . 'Readme.md'],
			'multibyte name' => [str_repeat('ä', 51)],
		];
	}

	/**
	 * @dataProvider tooLongPathProvider
	 */
	public function testStoreRejectsTooLongPaths(string $path): void {
		$this->tempManager->expects($this->never())->method('getTemporaryFolder');

		try {
			$this->archiver->store($this->staticSiteId, [$path => $this->makeFile('content')]);
			$this->fail('Expected UnprocessableEntityException');
		} catch (UnprocessableEntityException $e) {
			$this->assertStringContainsString($path, $e->getMessage());
		}
	}

	public function testStoreRejectsEmptyFileList(): void {
		$this->tempManager->expects($this->never())->method('getTemporaryFolder');
		$this->appData->expects($this->never())->method('getFolder');

		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('There is no content to publish.');
		$this->archiver->store($this->staticSiteId, []);
	}

	public function testStoreFailsAndCleansUpIfFileCannotBeRead(): void {
		$file = $this->createMock(File::class);
		$file->method('fopen')->willReturn(false);
		$this->appData->expects($this->never())->method('getFolder');

		try {
			$this->archiver->store($this->staticSiteId, ['Readme.md' => $file]);
			$this->fail('Expected ServiceException');
		} catch (ServiceException) {
		}

		$this->assertDirectoryDoesNotExist($this->tempFolder);
	}

	public function testStoreFailsIfTemporaryFolderCannotBeCreated(): void {
		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->method('getTemporaryFolder')->willReturn(false);
		$archiver = new StaticSiteArchiver($this->appData, $tempManager, $this->l10n);

		$this->expectException(ServiceException::class);
		$archiver->store($this->staticSiteId, ['Readme.md' => $this->makeFile('# Home')]);
	}

	public function testDeleteRemovesArchive(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->expects($this->once())->method('delete');

		$this->appData->method('getFolder')->willReturn($this->folder);
		$this->folder->method('fileExists')->with($this->staticSiteId . '.tar.gz')->willReturn(true);
		$this->folder->method('getFile')->willReturn($file);

		$this->archiver->delete($this->staticSiteId);
	}

	public function testDeleteIgnoresMissingArchive(): void {
		$this->appData->method('getFolder')->willReturn($this->folder);
		$this->folder->method('fileExists')->willReturn(false);
		$this->folder->expects($this->never())->method('getFile');

		$this->archiver->delete($this->staticSiteId);
	}

	public function testDeleteIgnoresMissingAppDataFolder(): void {
		$this->appData->method('getFolder')->willThrowException(new FilesNotFoundException());
		$this->appData->expects($this->never())->method('newFolder');

		$this->archiver->delete($this->staticSiteId);
	}
}
