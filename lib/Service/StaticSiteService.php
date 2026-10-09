<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Db\StaticSiteMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DBException;
use OCP\Files\File;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\Util;
use Psr\Log\LoggerInterface;
use Throwable;

class StaticSiteService {
	private const TITLE_MAX_LENGTH = 255;
	private const SLUG_MAX_LENGTH = 64;
	private const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
	// App config key for the maximum size of all files of a static site in bytes
	public const MAX_SIZE_CONFIG_KEY = 'static_site_max_size';
	public const MAX_SIZE_DEFAULT = 100 * 1024 * 1024;
	private const LARGEST_FILES_IN_MESSAGE = 3;

	public function __construct(
		private readonly StaticSiteMapper $staticSiteMapper,
		private readonly CollectiveService $collectiveService,
		private readonly PageService $pageService,
		private readonly SlugService $slugService,
		private readonly StaticSiteContentCollector $contentCollector,
		private readonly StaticSiteArchiver $archiver,
		private readonly LoggerInterface $logger,
		private readonly ITimeFactory $timeFactory,
		private readonly IAppConfig $appConfig,
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * Create a selection of pages of a collective as a static site and provide its archive.
	 *
	 * Title and slug default to the collective name. If the archive can't be provided,
	 * the static site is removed again.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish, incl. the collective's root page
	 *
	 * @throws NotFoundException Collective or one of the pages not found
	 * @throws NotPermittedException User is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected, invalid title or slug, slug already in use, path too long
	 * @throws ServiceException Archive couldn't be provided
	 * @throws DBException
	 */
	public function create(int $collectiveId, array $pageIds, string $userId, ?string $title = null, ?string $slug = null): StaticSite {
		$pageIds = $this->normalizePageIds($pageIds);
		$collective = $this->getEditableCollective($collectiveId, $userId);

		$title = trim($title ?? $collective->getName());
		$slug ??= $this->generateSlug($collective->getName());
		$this->validateTitle($title);
		$this->validateSlug($slug);
		$this->verifyPagesBelongToCollective($collectiveId, $pageIds, $userId);

		try {
			$staticSite = $this->staticSiteMapper->create($collectiveId, $pageIds, $title, $slug, $userId);
		} catch (DBException $e) {
			if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			throw new UnprocessableEntityException('Slug is already in use: ' . $slug, 0, $e);
		}

		try {
			$this->buildArchive($staticSite);
		} catch (ServiceException $e) {
			$this->staticSiteMapper->delete($staticSite);
			$this->deleteArchive($staticSite);
			throw $e;
		}

		return $this->finishPublication($staticSite, $title, $pageIds);
	}

	/**
	 * Update title and page selection of a static site and provide its archive again.
	 *
	 * The slug can't be changed as it is part of the public URL. If the archive can't be
	 * provided, the static site and its previous archive remain unchanged.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish, incl. the collective's root page
	 *
	 * @throws NotFoundException Collective, static site or one of the pages not found
	 * @throws NotPermittedException User is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected, invalid title, publication in progress, path too long
	 * @throws ServiceException Archive couldn't be provided
	 * @throws DBException
	 */
	public function update(int $collectiveId, int $id, array $pageIds, string $title, string $userId): StaticSite {
		$pageIds = $this->normalizePageIds($pageIds);
		$this->getEditableCollective($collectiveId, $userId);

		$staticSite = $this->staticSiteMapper->findByIdAndCollectiveId($id, $collectiveId);
		if ($staticSite->isInProgress($this->timeFactory->getTime())) {
			throw new UnprocessableEntityException('Publication of static site is already in progress');
		}

		$title = trim($title);
		$this->validateTitle($title);
		$this->verifyPagesBelongToCollective($collectiveId, $pageIds, $userId);

		$previousStatus = $staticSite->getStatus();
		if (!$this->staticSiteMapper->startPublication($staticSite)) {
			throw new UnprocessableEntityException('Publication of static site is already in progress');
		}

		try {
			$this->buildArchive($staticSite);
		} catch (ServiceException $e) {
			$this->restoreStatus($staticSite, $previousStatus);
			throw $e;
		}

		return $this->finishPublication($staticSite, $title, $pageIds);
	}

	/**
	 * @throws NotFoundException Collective or static site not found
	 * @throws NotPermittedException User is not allowed to edit the collective
	 * @throws DBException
	 */
	public function delete(int $collectiveId, int $id, string $userId): void {
		$this->getEditableCollective($collectiveId, $userId);

		$staticSite = $this->staticSiteMapper->findByIdAndCollectiveId($id, $collectiveId);
		$this->staticSiteMapper->delete($staticSite);
		$this->deleteArchive($staticSite);
	}

	/**
	 * @return StaticSite[]
	 *
	 * @throws NotFoundException Collective not found
	 * @throws NotPermittedException User is not allowed to access the collective
	 */
	public function getStaticSites(int $collectiveId, string $userId): array {
		$this->collectiveService->getCollective($collectiveId, $userId);

		return $this->staticSiteMapper->findByCollectiveId($collectiveId);
	}

	/**
	 * @throws UnprocessableEntityException Content can't be stored in the archive, e.g. too large or a path is too long
	 * @throws ServiceException
	 */
	private function buildArchive(StaticSite $staticSite): void {
		try {
			$files = $this->contentCollector->collect($staticSite->getCollectiveId());
			$this->validateSize($files);
			$this->archiver->store($staticSite->getStaticSiteId(), $files);
		} catch (UnprocessableEntityException $e) {
			throw $e;
		} catch (Throwable $e) {
			// Catch everything, otherwise the static site would remain in progress
			throw new ServiceException('Failed to provide static site archive', 0, $e);
		}

		$this->logger->info('Provided static site archive', $this->getLogContext($staticSite) + ['fileCount' => count($files)]);
	}

	/**
	 * The size of the files is checked instead of the archive, so too large sites fail before building.
	 * As most attachments are compressed already, the archive won't be much smaller anyway.
	 *
	 * @param array<string, File> $files
	 *
	 * @throws UnprocessableEntityException
	 */
	private function validateSize(array $files): void {
		$maxSize = $this->appConfig->getValueInt('collectives', self::MAX_SIZE_CONFIG_KEY, self::MAX_SIZE_DEFAULT);
		$sizes = array_map(static fn (File $file): int|float => $file->getSize(), $files);
		$totalSize = array_sum($sizes);
		if ($totalSize <= $maxSize) {
			return;
		}

		arsort($sizes);
		$largest = array_slice($sizes, 0, self::LARGEST_FILES_IN_MESSAGE, true);
		$largestFiles = array_map(
			// Numeric paths become integer array keys
			static fn (int|string $path, int|float $size): string => $path . ' (' . Util::humanFileSize($size) . ')',
			array_keys($largest),
			$largest,
		);
		throw new UnprocessableEntityException($this->l10n->t(
			'The website would be %1$s, but at most %2$s are allowed. Please remove or shrink large files, e.g. %3$s',
			[Util::humanFileSize($totalSize), Util::humanFileSize($maxSize), implode(', ', $largestFiles)],
		));
	}

	/**
	 * @param list<int> $pageIds
	 *
	 * @throws NotFoundException Static site was deleted meanwhile
	 * @throws UnprocessableEntityException
	 * @throws DBException
	 */
	private function finishPublication(StaticSite $staticSite, string $title, array $pageIds): StaticSite {
		try {
			return $this->staticSiteMapper->finishPublication($staticSite, $title, $pageIds);
		} catch (NotFoundException $e) {
			$this->deleteArchive($staticSite);
			throw $e;
		}
	}

	/**
	 * @throws DBException
	 */
	private function restoreStatus(StaticSite $staticSite, string $status): void {
		try {
			$this->staticSiteMapper->updateStatus($staticSite->getId(), $status);
		} catch (NotFoundException) {
			// Static site was deleted meanwhile, nothing to restore
		}
	}

	/**
	 * Failing to delete the archive only leaves an orphaned file, so it doesn't throw.
	 */
	private function deleteArchive(StaticSite $staticSite): void {
		try {
			$this->archiver->delete($staticSite->getStaticSiteId());
		} catch (ServiceException $e) {
			$this->logger->warning('Failed to delete static site archive', $this->getLogContext($staticSite) + ['exception' => $e]);
		}
	}

	private function getLogContext(StaticSite $staticSite): array {
		return [
			'staticSiteId' => $staticSite->getStaticSiteId(),
			'collectiveId' => $staticSite->getCollectiveId(),
		];
	}

	/**
	 * @return list<int>
	 *
	 * @throws UnprocessableEntityException
	 */
	private function normalizePageIds(array $pageIds): array {
		$pageIds = array_map(intval(...), array_values($pageIds));
		if (empty($pageIds)) {
			throw new UnprocessableEntityException('No pages selected for publishing');
		}
		return $pageIds;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getEditableCollective(int $collectiveId, string $userId): Collective {
		$collective = $this->collectiveService->getCollective($collectiveId, $userId);
		if (!$collective->canEdit()) {
			throw new NotPermittedException('Not allowed to edit collective');
		}
		return $collective;
	}

	private function generateSlug(string $name): string {
		$slug = strtolower($this->slugService->generateSlug($name));
		return trim(substr($slug, 0, self::SLUG_MAX_LENGTH), '-');
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	private function validateTitle(string $title): void {
		if ($title === '' || mb_strlen($title) > self::TITLE_MAX_LENGTH) {
			throw new UnprocessableEntityException('Title must be between 1 and ' . self::TITLE_MAX_LENGTH . ' characters');
		}
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	private function validateSlug(string $slug): void {
		if (strlen($slug) > self::SLUG_MAX_LENGTH || !preg_match(self::SLUG_PATTERN, $slug)) {
			throw new UnprocessableEntityException('Slug must consist of 1 to ' . self::SLUG_MAX_LENGTH . ' lowercase ASCII letters and numbers, separated by single hyphens');
		}
	}

	/**
	 * @param list<int> $pageIds
	 *
	 * @throws NotFoundException If a page doesn't belong to the collective
	 * @throws NotPermittedException
	 */
	private function verifyPagesBelongToCollective(int $collectiveId, array $pageIds, string $userId): void {
		$validPageIds = array_map(
			static fn ($pageInfo) => $pageInfo->getId(),
			$this->pageService->findAll($collectiveId, $userId)
		);

		$unknownPageIds = array_diff($pageIds, $validPageIds);
		if (!empty($unknownPageIds)) {
			throw new NotFoundException('Page(s) not found in collective: ' . implode(', ', $unknownPageIds));
		}
	}
}
