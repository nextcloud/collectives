<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Db\StaticSiteMapper;
use OCP\DB\Exception as DBException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Deletes static sites at the static site generator and locally.
 *
 * Independent of the collective, so it can also be used while a collective is purged.
 */
class StaticSiteRemover {
	public function __construct(
		private readonly StaticSiteMapper $staticSiteMapper,
		private readonly StaticSiteArchiver $archiver,
		private readonly StaticSiteGeneratorClient $generatorClient,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Delete a static site. The static site generator is asked even if the static site never got
	 * published, as it may know it anyway, e.g. while its first build is running. If the generator
	 * doesn't know it, it is deleted right away.
	 *
	 * Otherwise it is deleted asynchronously: it gets status deleting and a new build ID, so the
	 * status callback of the deletion can't be mixed up with a late one of a previous build. It is
	 * deleted once the static site generator reports it as deleted. Until then its slug stays
	 * reserved, as the generator still uses it.
	 *
	 * If the generator doesn't accept the request, the static site remains unchanged. If it didn't
	 * answer after the request was sent, the static site stays deleting, as the generator may have
	 * accepted the request anyway.
	 *
	 * @return bool True if the static site got deleted right away, false if it is being deleted
	 *
	 * @throws UnprocessableEntityException The static site was changed meanwhile
	 * @throws StaticSiteGeneratorException Generator not configured, unavailable or didn't accept the request
	 * @throws DBException
	 */
	public function remove(StaticSite $staticSite): bool {
		$this->generatorClient->assertConfigured();

		$buildId = Uuid::v7()->toRfc4122();
		if (!$this->staticSiteMapper->startDeletion($staticSite, $buildId)) {
			throw new UnprocessableEntityException('Static site was changed meanwhile');
		}
		$previous = clone $staticSite;
		$staticSite->setBuildId($buildId);
		$staticSite->setStatus(StaticSite::STATUS_DELETING);

		try {
			$known = $this->generatorClient->delete($staticSite);
		} catch (StaticSiteGeneratorTimeoutException $e) {
			throw $e;
		} catch (StaticSiteGeneratorException $e) {
			$this->restoreStatus($previous);
			throw $e;
		}

		if (!$known) {
			return $this->removeDeleted($staticSite);
		}
		// The static site generator doesn't download archives of a deleted static site anymore
		$this->deleteArchives($staticSite);
		return false;
	}

	/**
	 * Delete all static sites of a collective, e.g. when it gets purged.
	 *
	 * Doesn't throw, as purging must not fail. Static sites that can't be deleted at the static
	 * site generator right now are kept, so their slugs stay reserved until the DeleteOrphanedStaticSites
	 * background job deleted them.
	 */
	public function removeAllOfCollective(int $collectiveId): void {
		try {
			$staticSites = $this->staticSiteMapper->findByCollectiveId($collectiveId);
		} catch (DBException $e) {
			$this->logger->warning('Failed to find static sites of collective to delete them', ['collectiveId' => $collectiveId, 'exception' => $e]);
			return;
		}
		$this->removeEach($staticSites);
	}

	/**
	 * Retry deleting the static sites of collectives that got purged, as nobody can delete them
	 * manually anymore. Static sites that are being deleted are left alone until their deletion
	 * is concluded or aborted.
	 *
	 * Doesn't throw, failures are retried next time. If the static site generator isn't configured,
	 * this is logged once instead of for each static site.
	 */
	public function removeAllOrphaned(int $now): void {
		try {
			$staticSites = $this->staticSiteMapper->findAllOrphaned();
		} catch (DBException $e) {
			$this->logger->warning('Failed to find orphaned static sites', ['exception' => $e]);
			return;
		}
		$staticSites = array_filter($staticSites, static fn (StaticSite $staticSite): bool => !$staticSite->isInProgress($now));
		if ($staticSites === []) {
			return;
		}

		try {
			$this->generatorClient->assertConfigured();
		} catch (StaticSiteGeneratorNotConfiguredException) {
			$this->logger->warning('Cannot delete orphaned static sites, the static site generator is not configured', ['count' => count($staticSites)]);
			return;
		}
		$this->removeEach($staticSites);
	}

	/**
	 * @param StaticSite[] $staticSites
	 */
	private function removeEach(array $staticSites): void {
		foreach ($staticSites as $staticSite) {
			try {
				$this->remove($staticSite);
			} catch (ServiceException|DBException $e) {
				$this->logger->warning('Failed to delete static site of collective', $this->getLogContext($staticSite) + ['exception' => $e]);
			}
		}
	}

	/**
	 * Delete the static site once the static site generator deleted it, unless another
	 * request started meanwhile.
	 *
	 * @return bool False if another request started meanwhile
	 *
	 * @throws DBException
	 */
	public function removeDeleted(StaticSite $staticSite): bool {
		if (!$this->staticSiteMapper->deleteIfCurrentBuild($staticSite)) {
			$this->logger->warning('Not deleting static site, it was changed meanwhile', $this->getLogContext($staticSite));
			return false;
		}
		$this->deleteArchives($staticSite);
		return true;
	}

	/**
	 * @throws DBException
	 */
	private function restoreStatus(StaticSite $previous): void {
		try {
			$this->staticSiteMapper->restoreStatus($previous);
		} catch (NotFoundException) {
			// Static site was deleted meanwhile, nothing to restore
		}
	}

	/**
	 * Delete the archive folder of the static site in app data. Its website folder is deleted by
	 * the static site generator. Failing to delete the archives only leaves orphaned files, so it
	 * doesn't throw.
	 */
	public function deleteArchives(StaticSite $staticSite): void {
		try {
			$this->archiver->deleteAll($staticSite->getStaticSiteId());
		} catch (ServiceException $e) {
			$this->logger->warning('Failed to delete static site archives', $this->getLogContext($staticSite) + ['exception' => $e]);
		}
	}

	private function getLogContext(StaticSite $staticSite): array {
		return [
			'staticSiteId' => $staticSite->getStaticSiteId(),
			'buildId' => $staticSite->getBuildId(),
			'collectiveId' => $staticSite->getCollectiveId(),
		];
	}
}
