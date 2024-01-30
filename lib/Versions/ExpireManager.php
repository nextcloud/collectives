<?php

declare(strict_types=1);

namespace OCA\Collectives\Versions;

use OCA\Collectives\Service\MissingDependencyException;
use OCA\Files_Versions\Expiration;
use OCA\Files_Versions\Versions\IVersion;
use OCP\AppFramework\QueryException;
use Psr\Container\ContainerInterface;

class ExpireManager {
	public const MAX_VERSIONS_PER_INTERVAL = [
		// first 10sec, one version every 2sec
		1 => ['intervalEndsAfter' => 10, 'step' => 2],
		// next minute, one version every 10sec
		2 => ['intervalEndsAfter' => 60, 'step' => 10],
		// next hour, one version every minute
		3 => ['intervalEndsAfter' => 3600, 'step' => 60],
		// next 24h, one version every hour
		4 => ['intervalEndsAfter' => 86400, 'step' => 3600],
		// next 30days, one version per day
		5 => ['intervalEndsAfter' => 2592000, 'step' => 86400],
		// until the end one version per week
		6 => ['intervalEndsAfter' => -1, 'step' => 604800],
	];

	private ?Expiration $expiration = null;
	private string $dependencyInjectionError = '';

	public function __construct(ContainerInterface $appContainer) {
		try {
			$this->expiration = $appContainer->get(Expiration::class);
		} catch (QueryException $e) {
			// Could not instantiate - probably files_versions app is disabled
			$this->dependencyInjectionError = $e->getMessage();
		}
	}

	/**
	 * get list of files we want to archive
	 */
	protected function getAutoExpireList(int $time, array $versions): array {
		if (!$versions) {
			return [];
		}
		$toDelete = []; // versions we want to delete

		// ensure the versions are sorted by newest first
		usort($versions, static fn (IVersion $a, IVersion $b) => $b->getTimestamp() <=> $a->getTimestamp());

		$interval = 1;
		$step = self::MAX_VERSIONS_PER_INTERVAL[$interval]['step'];
		if (self::MAX_VERSIONS_PER_INTERVAL[$interval]['intervalEndsAfter'] === -1) {
			$nextInterval = -1;
		} else {
			$nextInterval = $time - self::MAX_VERSIONS_PER_INTERVAL[$interval]['intervalEndsAfter'];
		}

		$firstVersion = array_shift($versions);
		$prevTimestamp = $firstVersion->getTimestamp();
		$nextVersion = $firstVersion->getTimestamp() - $step;

		foreach ($versions as $version) {
			$newInterval = true;
			while ($newInterval && $interval <= 5) {
				if ($nextInterval === -1 || $prevTimestamp > $nextInterval) {
					if ($version->getTimestamp() > $nextVersion) {
						// distance between two versions is too small, mark to delete
						$toDelete[] = $version;
					} else {
						$nextVersion = $version->getTimestamp() - $step;
						$prevTimestamp = $version->getTimestamp();
					}
					$newInterval = false; // version checked so we can move to the next one
				} else { // time to move on to the next interval
					$interval++;
					$step = self::MAX_VERSIONS_PER_INTERVAL[$interval]['step'];
					$nextVersion = $prevTimestamp - $step;
					if (self::MAX_VERSIONS_PER_INTERVAL[$interval]['intervalEndsAfter'] === -1) {
						$nextInterval = -1;
					} else {
						$nextInterval = $time - self::MAX_VERSIONS_PER_INTERVAL[$interval]['intervalEndsAfter'];
					}
					$newInterval = true; // we changed the interval -> check same version with new interval
				}
			}
		}

		return $toDelete;
	}

	/**
	 * @throws MissingDependencyException
	 */
	public function getExpiredVersion(array $versions, int $time, bool $quotaExceeded): array {
		if (is_null($this->expiration)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}

		if ($this->expiration->shouldAutoExpire()) {
			$autoExpire = $this->getAutoExpireList($time, $versions);
		} else {
			$autoExpire = [];
		}

		$versionsLeft = array_udiff($versions, $autoExpire, static fn (IVersion $a, IVersion $b) => ($a->getRevisionId() <=> $b->getRevisionId()) *
			($a->getSourceFile()->getId() <=> $b->getSourceFile()->getId()));

		$expired = array_filter($versionsLeft, fn (IVersion $version) => $this->expiration->isExpired($version->getTimestamp(), $quotaExceeded));

		return array_merge($autoExpire, $expired);
	}
}
