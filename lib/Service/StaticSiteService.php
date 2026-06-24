<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;

/**
 * Minimal example of rendering a static site with the Hugo SSG.
 *
 * The flow is deliberately simple to show the basic idea:
 *   1. run the Hugo binary (a syscall) to build HTML into a temporary directory
 *   2. store the generated HTML in the user's Nextcloud files
 *
 * Hugo is a single static binary and `--noBuildLock` keeps it from writing into
 * the (read-only) app directory, so no project copy or cache handling is needed.
 */
class StaticSiteService {
	/** Portable Hugo binary, relative to the app root (see ssg/fetch-hugo.sh). */
	private const HUGO_BINARY = 'ssg/.runtime/hugo';
	/** The Hugo project directory, relative to the app root. */
	private const HUGO_DIR = 'ssg/hugo';
	/** Folder (in the user's files) where generated sites are stored. */
	private const OUTPUT_BASE_DIR = 'Collectives Static Sites';

	public function __construct(
		private IRootFolder $rootFolder,
	) {
	}

	/**
	 * Render the bundled sample site and store it in the user's files.
	 *
	 * @return array{path: string} Path of the generated file, relative to the user's files
	 *
	 * @throws MissingDependencyException
	 * @throws ServiceException
	 */
	public function generateSampleSite(string $userId, ?string $title = null): array {
		$appRoot = dirname(__DIR__, 2);
		$hugo = $appRoot . '/' . self::HUGO_BINARY;
		if (!is_executable($hugo)) {
			throw new MissingDependencyException('Hugo binary not found. Run `make ssg-setup` to install it.');
		}

		$title = ($title !== null && trim($title) !== '') ? trim($title) : 'Collectives';
		$outDir = sys_get_temp_dir() . '/collectives-ssg-' . bin2hex(random_bytes(6));

		try {
			$this->runHugo($hugo, $appRoot . '/' . self::HUGO_DIR, $outDir, $userId, $title);
			return $this->storeHtml($userId, $outDir . '/index.html', $title);
		} finally {
			@unlink($outDir . '/index.html');
			@rmdir($outDir);
		}
	}

	/**
	 * Run the Hugo binary to build the site into $outDir.
	 *
	 * @throws ServiceException on a failed build
	 */
	private function runHugo(string $hugo, string $sourceDir, string $outDir, string $userId, string $title): void {
		$command = [$hugo, '--source', $sourceDir, '--destination', $outDir, '--noBuildLock', '--quiet'];
		$env = [
			'PATH' => '/usr/bin:/bin',
			'HOME' => sys_get_temp_dir(),
			// Injected into the Hugo templates (read there via os.Getenv).
			'COLLECTIVES_SSG_TITLE' => $title,
			'COLLECTIVES_SSG_USER' => $userId,
		];

		$descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
		$process = proc_open($command, $descriptors, $pipes, $sourceDir, $env);
		if (!is_resource($process)) {
			throw new ServiceException('Could not start the Hugo process');
		}

		stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		if (proc_close($process) !== 0) {
			throw new ServiceException('Hugo build failed: ' . trim($error));
		}
	}

	/**
	 * Store the generated HTML as a file in the user's files.
	 *
	 * @return array{path: string}
	 *
	 * @throws ServiceException
	 */
	private function storeHtml(string $userId, string $htmlFile, string $title): array {
		$html = file_get_contents($htmlFile);
		if ($html === false) {
			throw new ServiceException('Hugo did not produce any output');
		}

		$userFolder = $this->rootFolder->getUserFolder($userId);
		$baseFolder = $userFolder->nodeExists(self::OUTPUT_BASE_DIR)
			? $userFolder->get(self::OUTPUT_BASE_DIR)
			: $userFolder->newFolder(self::OUTPUT_BASE_DIR);
		if (!$baseFolder instanceof Folder) {
			throw new ServiceException('Output location is not a folder');
		}

		$name = $this->safeName($title) . '-' . date('Ymd-His') . '.html';
		$file = $baseFolder->newFile($name, $html);

		return ['path' => $userFolder->getRelativePath($file->getPath()) ?? self::OUTPUT_BASE_DIR . '/' . $name];
	}

	private function safeName(string $name): string {
		$clean = trim(preg_replace('/[^\p{L}\p{N} _-]+/u', '', $name) ?? '');
		return $clean !== '' ? $clean : 'Collectives';
	}
}
