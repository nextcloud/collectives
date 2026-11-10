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

	private function makeFile(string $name, int $id = 0): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn($name);
		$file->method('getId')->willReturn($id);
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

	/**
	 * Builds a collective folder with the following layout:
	 *
	 * Readme.md (id=1)
	 * .attachments.1/image.png
	 * Subpage/
	 *   Readme.md (id=2)
	 *   file.pdf                 (folder attachment of Subpage)
	 *   Empty/                   (folder without an index page)
	 *   Leaf.md (id=4)           (leaf subpage of Subpage)
	 *   .attachments.4/doc.txt   (attachments of Leaf.md)
	 *   Subsubpage/
	 *     Readme.md (id=3)
	 */
	private function makeCollectiveFolderWithPages(): Folder&MockObject {
		$readme = $this->makeFile('Readme.md', 1);
		$image = $this->makeFile('image.png');
		$subReadme = $this->makeFile('Readme.md', 2);
		$pdf = $this->makeFile('file.pdf');
		$leaf = $this->makeFile('Leaf.md', 4);
		$doc = $this->makeFile('doc.txt');
		$subSubReadme = $this->makeFile('Readme.md', 3);

		return $this->makeFolder('42', [
			$readme,
			$this->makeFolder('.attachments.1', [$image]),
			$this->makeFolder('Subpage', [
				$subReadme,
				$pdf,
				$this->makeFolder('Empty', []),
				$leaf,
				$this->makeFolder('.attachments.4', [$doc]),
				$this->makeFolder('Subsubpage', [$subSubReadme]),
			]),
		]);
	}

	public function testCollectReturnsAllSelectedPagesWithRelativePaths(): void {
		$this->collectiveFolderManager->method('getFolder')
			->with($this->collectiveId)
			->willReturn($this->makeCollectiveFolderWithPages());

		$files = $this->collector->collect($this->collectiveId, [1, 2, 3, 4]);

		$this->assertSame([
			'Readme.md',
			'.attachments.1/image.png',
			'Subpage/Readme.md',
			'Subpage/file.pdf',
			'Subpage/Leaf.md',
			'Subpage/.attachments.4/doc.txt',
			'Subpage/Subsubpage/Readme.md',
		], array_keys($files));
	}

	public function testCollectExcludesUnselectedLeafPageAndItsAttachments(): void {
		$this->collectiveFolderManager->method('getFolder')
			->willReturn($this->makeCollectiveFolderWithPages());

		// Leaf.md (id 4) is not selected
		$files = $this->collector->collect($this->collectiveId, [1, 2, 3]);

		$this->assertSame([
			'Readme.md',
			'.attachments.1/image.png',
			'Subpage/Readme.md',
			'Subpage/file.pdf',
			'Subpage/Subsubpage/Readme.md',
		], array_keys($files));
	}

	public function testCollectExcludesUnselectedSubpageFolderAndItsWholeSubtree(): void {
		$this->collectiveFolderManager->method('getFolder')
			->willReturn($this->makeCollectiveFolderWithPages());

		// Only the root page is selected, "Subpage" (id 2) and everything below it is left out
		$files = $this->collector->collect($this->collectiveId, [1]);

		$this->assertSame([
			'Readme.md',
			'.attachments.1/image.png',
		], array_keys($files));
	}

	public function testCollectHandlesAttachmentsOfTheIndexPageOfASubpageFolder(): void {
		$readme = $this->makeFile('Readme.md', 1);
		$subReadme = $this->makeFile('Readme.md', 2);
		$attachment = $this->makeFile('image.png');

		$this->collectiveFolderManager->method('getFolder')
			->willReturn($this->makeFolder('42', [
				$readme,
				$this->makeFolder('Subpage', [
					$subReadme,
					// Attachments of the index page of the folder, not of a sibling page
					$this->makeFolder('.attachments.2', [$attachment]),
				]),
			]));

		$this->assertSame([
			'Readme.md' => $readme,
			'Subpage/Readme.md' => $subReadme,
			'Subpage/.attachments.2/image.png' => $attachment,
		], $this->collector->collect($this->collectiveId, [1, 2]));

		// Without the index page, its attachments are left out as well
		$this->assertSame([
			'Readme.md' => $readme,
		], $this->collector->collect($this->collectiveId, [1]));
	}

	public function testCollectSkipsHiddenFilesAndFolders(): void {
		$readme = $this->makeFile('Readme.md', 1);
		$subReadme = $this->makeFile('Readme.md', 2);
		$attachment = $this->makeFile('image.png');

		$this->collectiveFolderManager->method('getFolder')
			->willReturn($this->makeFolder('42', [
				$readme,
				$this->makeFile('.DS_Store'),
				$this->makeFolder('.templates', [$this->makeFile('Readme.md')]),
				$this->makeFolder('.attachments.1', [$attachment, $this->makeFile('.DS_Store')]),
				$this->makeFolder('Subpage', [
					$subReadme,
					$this->makeFile('._Readme.md'),
					// Hidden folders are skipped at every level, not just the top one
					$this->makeFolder('.templates', [$this->makeFile('note.txt')]),
					$this->makeFolder('.obsidian', [$this->makeFile('workspace.json')]),
				]),
			]));

		$this->assertSame([
			'Readme.md' => $readme,
			'.attachments.1/image.png' => $attachment,
			'Subpage/Readme.md' => $subReadme,
		], $this->collector->collect($this->collectiveId, [1, 2]));
	}

	public function testCollectSkipsHiddenFoldersWithAttachmentLikeName(): void {
		$readme = $this->makeFile('Readme.md', 1);

		$this->collectiveFolderManager->method('getFolder')
			->willReturn($this->makeFolder('42', [
				$readme,
				// Not an attachment folder: the suffix is not a page ID
				$this->makeFolder('.attachments.1backup', [$this->makeFile('image.png')]),
				$this->makeFolder('.attachments.', [$this->makeFile('image.png')]),
			]));

		$this->assertSame([
			'Readme.md' => $readme,
		], $this->collector->collect($this->collectiveId, [1]));
	}

	public function testCollectSkipsHiddenEntriesInFoldersWithoutIndexPage(): void {
		$readme = $this->makeFile('Readme.md', 1);
		$upload = $this->makeFile('slides.pdf');

		$this->collectiveFolderManager->method('getFolder')
			->willReturn($this->makeFolder('42', [
				$readme,
				// Not a page folder, so its content is kept as is - except hidden entries
				$this->makeFolder('Uploads', [
					$upload,
					$this->makeFile('.DS_Store'),
					$this->makeFolder('.git', [$this->makeFile('config')]),
				]),
			]));

		$this->assertSame([
			'Readme.md' => $readme,
			'Uploads/slides.pdf' => $upload,
		], $this->collector->collect($this->collectiveId, [1]));
	}

	public function testCollectThrowsIfCollectiveFolderIsMissing(): void {
		$this->collectiveFolderManager->method('getFolder')->willThrowException(new FilesNotFoundException());

		$this->expectException(FilesNotFoundException::class);
		$this->collector->collect($this->collectiveId, [1]);
	}
}
