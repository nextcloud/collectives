<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\StaticSiteContentCollector;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class StaticSiteContentCollectorTest extends TestCase {
	private CollectiveFolderManager&MockObject $collectiveFolderManager;
	private StaticSiteContentCollector $collector;

	private int $collectiveId = 42;

	protected function setUp(): void {
		parent::setUp();

		$this->collectiveFolderManager = $this->createMock(CollectiveFolderManager::class);
		$this->collector = new StaticSiteContentCollector($this->collectiveFolderManager);
	}

	private function makeFile(string $name): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($name);
		return $file;
	}

	/**
	 * @param list<File|Folder> $children
	 */
	private function makeFolder(string $name, array $children): Folder&MockObject {
		$folder = $this->createMock(Folder::class);
		$folder->method('getName')->willReturn($name);
		$folder->method('getDirectoryListing')->willReturn($children);
		return $folder;
	}

	public function testCollectReturnsAllFilesWithRelativePaths(): void {
		$readme = $this->makeFile('Readme.md');
		$image = $this->makeFile('image.png');
		$subReadme = $this->makeFile('Readme.md');
		$pdf = $this->makeFile('file.pdf');
		$subSubPage = $this->makeFile('Page.md');

		$this->collectiveFolderManager->method('getFolder')
			->with($this->collectiveId)
			->willReturn($this->makeFolder('42', [
				$readme,
				$this->makeFolder('.attachments.12', [$image]),
				$this->makeFolder('Subpage', [
					$subReadme,
					$pdf,
					$this->makeFolder('Empty', []),
					$this->makeFolder('Subsubpage', [$subSubPage]),
				]),
			]));

		$this->assertSame([
			'Readme.md' => $readme,
			'.attachments.12/image.png' => $image,
			'Subpage/Readme.md' => $subReadme,
			'Subpage/file.pdf' => $pdf,
			'Subpage/Subsubpage/Page.md' => $subSubPage,
		], $this->collector->collect($this->collectiveId));
	}

	public function testCollectSkipsTemplateFolderOnlyAtTopLevel(): void {
		$readme = $this->makeFile('Readme.md');
		$nestedTemplate = $this->makeFile('Readme.md');

		$this->collectiveFolderManager->method('getFolder')
			->willReturn($this->makeFolder('42', [
				$readme,
				$this->makeFolder('.templates', [$this->makeFile('Readme.md')]),
				$this->makeFolder('Subpage', [
					$this->makeFolder('.templates', [$nestedTemplate]),
				]),
			]));

		$this->assertSame([
			'Readme.md' => $readme,
			'Subpage/.templates/Readme.md' => $nestedTemplate,
		], $this->collector->collect($this->collectiveId));
	}

	public function testCollectThrowsIfCollectiveFolderIsMissing(): void {
		$this->collectiveFolderManager->method('getFolder')->willThrowException(new FilesNotFoundException());

		$this->expectException(FilesNotFoundException::class);
		$this->collector->collect($this->collectiveId);
	}
}
