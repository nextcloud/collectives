<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\AppInfo\Application;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Locates the Hugo binary and renders a prepared Hugo site to static HTML.
 */
class HugoService {
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Resolve the absolute path to the hugo binary.
	 *
	 * Uses the `hugo_binary` app config value if set, otherwise looks it up in $PATH.
	 *
	 * @throws MissingDependencyException
	 */
	public function getBinaryPath(): string {
		$configured = $this->appConfig->getValueString(Application::APP_NAME, 'hugo_binary', '');
		if ($configured !== '') {
			if (!is_file($configured) || !is_executable($configured)) {
				throw new MissingDependencyException('Configured hugo binary is not an executable file: ' . $configured);
			}
			return $configured;
		}

		$found = $this->findInPath();
		if ($found === null) {
			throw new MissingDependencyException(
				'Could not find the "hugo" binary. Install Hugo on the server or set the '
				. '"hugo_binary" app config value (occ config:app:set collectives hugo_binary --value=/path/to/hugo).'
			);
		}
		return $found;
	}

	private function findInPath(): ?string {
		$dirs = explode(PATH_SEPARATOR, getenv('PATH') ?: '');
		// Common locations in case PATH is minimal in the web server environment.
		$dirs = array_merge($dirs, ['/usr/local/bin', '/usr/bin', '/bin', '/snap/bin']);
		foreach (array_unique($dirs) as $dir) {
			if ($dir === '') {
				continue;
			}
			$candidate = rtrim($dir, '/') . '/hugo';
			if (is_file($candidate) && is_executable($candidate)) {
				return $candidate;
			}
		}
		return null;
	}

	/**
	 * Build the Hugo site located at $siteDir and return the path to the rendered output directory.
	 *
	 * @throws MissingDependencyException
	 * @throws NotPermittedException
	 */
	public function build(string $siteDir): string {
		$binary = $this->getBinaryPath();
		$publicDir = rtrim($siteDir, '/') . '/public';

		$command = [
			$binary,
			'--source', $siteDir,
			'--destination', $publicDir,
			'--logLevel', 'warn',
			'--cleanDestinationDir',
		];

		// Keep Hugo's cache/temp inside the (writable) site dir and away from the web user's home.
		$env = [
			'HOME' => $siteDir,
			'TMPDIR' => $siteDir,
			'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
		];

		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];

		$process = proc_open($command, $descriptors, $pipes, $siteDir, $env);
		if (!is_resource($process)) {
			throw new NotPermittedException('Failed to start the hugo process for collective export');
		}

		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]) ?: '';
		$stderr = stream_get_contents($pipes[2]) ?: '';
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exitCode = proc_close($process);

		if ($exitCode !== 0) {
			$this->logger->error('Collective export: hugo build failed', [
				'exitCode' => $exitCode,
				'stdout' => $stdout,
				'stderr' => $stderr,
			]);
			$detail = trim($stderr) !== '' ? trim($stderr) : trim($stdout);
			throw new NotPermittedException('Hugo build failed for collective export: ' . $detail);
		}

		return $publicDir;
	}
}
