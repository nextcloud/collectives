<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

class UserFolderEncryptionKeyRepairResult {
	private int $targetPaths = 0;
	private int $sourceTreesFound = 0;
	private int $createdDirectories = 0;
	private int $copiedFiles = 0;
	private int $existingFilesSkipped = 0;

	/**
	 * @var list<string>
	 */
	private array $warnings = [];

	public function __construct(
		private readonly bool $dryRun,
	) {
	}

	public function isDryRun(): bool {
		return $this->dryRun;
	}

	public function addTargetPath(): void {
		$this->targetPaths++;
	}

	public function getTargetPaths(): int {
		return $this->targetPaths;
	}

	public function addSourceTree(): void {
		$this->sourceTreesFound++;
	}

	public function getSourceTreesFound(): int {
		return $this->sourceTreesFound;
	}

	public function addCreatedDirectory(): void {
		$this->createdDirectories++;
	}

	public function getCreatedDirectories(): int {
		return $this->createdDirectories;
	}

	public function addCopiedFile(): void {
		$this->copiedFiles++;
	}

	public function getCopiedFiles(): int {
		return $this->copiedFiles;
	}

	public function addExistingFileSkipped(): void {
		$this->existingFilesSkipped++;
	}

	public function getExistingFilesSkipped(): int {
		return $this->existingFilesSkipped;
	}

	public function addWarning(string $warning): void {
		$this->warnings[] = $warning;
	}

	/**
	 * @return list<string>
	 */
	public function getWarnings(): array {
		return $this->warnings;
	}

	public function hasWarnings(): bool {
		return $this->warnings !== [];
	}
}
