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
 * Deletes static sites, also at the static site generator if they got published.
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
	 * Delete a static site. One that never got published is deleted right away.
	 *
	 * One that got published is deleted asynchronously: it gets status deleting and a new build ID,
	 * so the status callback of the deletion can't be mixed up with a late one of a previous build.
	 * It is deleted once the static site generator reports it as deleted. Until then its slug stays
	 * reserved, as the generator still uses it.
	 *
	 * If the generator rejects the request, the static site remains unchanged. If it is unavailable,
	 * the static site stays deleting, as the generator may have accepted the request anyway.
	 *
	 * @throws UnprocessableEntityException The static site was changed meanwhile
	 * @throws StaticSiteGeneratorException Generator not configured, unavailable or didn't accept the request
	 * @throws DBException
	 */
	public function remove(StaticSite $staticSite): void {
		if (!$staticSite->isPublished()) {
			$this->removeLocally($staticSite);
			return;
		}
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
		} catch (StaticSiteGeneratorUnavailableException $e) {
			throw $e;
		} catch (StaticSiteGeneratorException $e) {
			$this->restoreStatus($previous);
			throw $e;
		}

		if (!$known) {
			$this->removeDeleted($staticSite);
			return;
		}
		// The static site generator doesn't download archives of a deleted static site anymore
		$this->deleteArchives($staticSite);
	}

	/**
	 * Delete all static sites of a collective, e.g. when it gets purged.
	 *
	 * Doesn't throw, as purging must not fail. Static sites that can't be deleted at the static
	 * site generator right now are kept, so their slugs stay reserved until deleting them is retried.
	 */
	public function removeAllOfCollective(int $collectiveId): void {
		try {
			$staticSites = $this->staticSiteMapper->findByCollectiveId($collectiveId);
		} catch (DBException $e) {
			$this->logger->warning('Failed to find static sites of collective to delete them', ['collectiveId' => $collectiveId, 'exception' => $e]);
			return;
		}

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
	 * @throws DBException
	 */
	public function removeDeleted(StaticSite $staticSite): void {
		if (!$this->staticSiteMapper->deleteIfCurrentBuild($staticSite)) {
			$this->logger->warning('Not deleting static site, it was changed meanwhile', $this->getLogContext($staticSite));
			return;
		}
		$this->deleteArchives($staticSite);
	}

	/**
	 * @throws DBException
	 */
	private function removeLocally(StaticSite $staticSite): void {
		$this->staticSiteMapper->delete($staticSite);
		$this->deleteArchives($staticSite);
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
	private function deleteArchives(StaticSite $staticSite): void {
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
