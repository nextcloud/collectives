<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

interface IProgressReporter {
	public function writeInfo(string $message): void;

	public function writeInfoVerbose(string $message): void;

	public function writeError(string $message): void;

	public function writeErrorVerbose(string $message): void;
}
