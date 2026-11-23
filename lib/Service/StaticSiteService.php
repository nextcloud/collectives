<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\ConfigLexicon;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Db\StaticSiteMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DBException;
use OCP\Files\File;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\Util;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

class StaticSiteService {
	private const TITLE_MAX_LENGTH = 255;
	private const SLUG_MAX_LENGTH = 64;
	private const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
	// Length of the published_url column
	private const PUBLISHED_URL_MAX_LENGTH = 255;
	private const LARGEST_FILES_IN_MESSAGE = 3;
	private const ALLOWED_CALLBACK_STATUSES = [
		StaticSite::STATUS_PUBLISHED,
		StaticSite::STATUS_FAILED,
	];

	public function __construct(
		private readonly StaticSiteMapper $staticSiteMapper,
		private readonly CollectiveService $collectiveService,
		private readonly PageService $pageService,
		private readonly SlugService $slugService,
		private readonly StaticSiteContentCollector $contentCollector,
		private readonly StaticSiteArchiver $archiver,
		private readonly StaticSiteGeneratorClient $generatorClient,
		private readonly LoggerInterface $logger,
		private readonly ITimeFactory $timeFactory,
		private readonly IAppConfig $appConfig,
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * Create a selection of pages of a collective as a static site, provide its archive and
	 * ask the static site generator to build it.
	 *
	 * Title defaults to the collective name. Slug stays empty. If the archive can't be provided, the
	 * static site is removed again. If the generator doesn't accept the build, the static site
	 * is stored with status failed, so publishing it can be retried.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish; their ancestor pages (incl. the
	 *                           collective's root page) are added automatically if missing
	 *
	 * @throws NotFoundException Collective or one of the pages not found
	 * @throws NotPermittedException Publish feature is disabled or user is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected, invalid title or slug, path too long
	 * @throws StaticSiteSlugTakenException Slug is already used by another static site
	 * @throws StaticSitePublicationInProgressException A static site of the collective is being published
	 * @throws StaticSiteGeneratorException Generator not configured or didn't accept the build
	 * @throws ServiceException Archive couldn't be provided
	 * @throws DBException
	 */
	public function create(int $collectiveId, array $pageIds, string $userId, ?string $title = null, ?string $slug = null): StaticSite {
		$this->assertPublishFeatureEnabled();
		$pageIds = $this->normalizePageIds($pageIds);
		$collective = $this->getEditableCollective($collectiveId, $userId);
		$this->assertNoPublicationInProgress($collectiveId);

		$title = trim($title ?? $collective->getName());
		$slug ??= $this->generateSlug($collective->getName());
		$this->validateTitle($title);
		$this->validateSlug($slug);
		$pageIds = $this->resolvePageIdsWithAncestors($collectiveId, $pageIds, $userId);
		$this->generatorClient->assertConfigured();

		// TODO(Publish): Also check the slug against the publish service once it provides an endpoint
		try {
			$staticSite = $this->staticSiteMapper->create($collectiveId, $pageIds, $title, $slug, $userId);
		} catch (DBException $e) {
			if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			throw new StaticSiteSlugTakenException('Slug is already taken: ' . $slug, 0, $e);
		}

		try {
			$this->buildArchive($staticSite, $pageIds);
		} catch (ServiceException $e) {
			$this->staticSiteMapper->delete($staticSite);
			$this->deleteAllArchives($staticSite);
			throw $e;
		}

		try {
			$this->generatorClient->build($staticSite, $title);
		} catch (StaticSiteGeneratorException $e) {
			$this->storeGeneratorFailure($staticSite, $title, $pageIds, $e);
			throw $e;
		}

		return $this->updateSettingsAndStatus($staticSite, $title, $pageIds, StaticSite::STATUS_PROVIDED);
	}

	/**
	 * Update title, page selection and slug of a static site, provide its archive again and
	 * ask the static site generator to build it.
	 *
	 * The slug can only be changed until the static site got published, as it is part of the
	 * public URL. If the archive can't be provided, the static site and its previous archive
	 * remain unchanged. If the generator doesn't accept the build, the settings are stored
	 * with status failed.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish; their ancestor pages (incl. the
	 *                           collective's root page) are added automatically if missing
	 * @param ?string $slug New slug, null keeps the current one
	 *
	 * @throws NotFoundException Collective, static site or one of the pages not found
	 * @throws NotPermittedException Publish feature is disabled or user is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected, invalid title or slug, slug of a published static site changed, publication started concurrently, path too long
	 * @throws StaticSiteSlugTakenException Slug is already used by another static site
	 * @throws StaticSitePublicationInProgressException A static site of the collective is being published
	 * @throws StaticSiteGeneratorException Generator not configured or didn't accept the build
	 * @throws ServiceException Archive couldn't be provided
	 * @throws DBException
	 */
	public function update(int $collectiveId, int $id, array $pageIds, string $title, string $userId, ?string $slug = null): StaticSite {
		$this->assertPublishFeatureEnabled();
		$pageIds = $this->normalizePageIds($pageIds);
		$this->getEditableCollective($collectiveId, $userId);

		$staticSite = $this->staticSiteMapper->findByIdAndCollectiveId($id, $collectiveId);
		$this->assertNoPublicationInProgress($collectiveId);

		$title = trim($title);
		$this->validateTitle($title);
		$slug = $this->getChangedSlug($staticSite, $slug);
		$pageIds = $this->resolvePageIdsWithAncestors($collectiveId, $pageIds, $userId);
		$this->generatorClient->assertConfigured();

		$buildId = Uuid::v7()->toRfc4122();
		// TODO(Publish): Also check the slug against the publish service once it provides an endpoint
		try {
			$updated = $this->staticSiteMapper->updateStatusIfUnchanged($staticSite, StaticSite::STATUS_PENDING, $buildId, $slug);
		} catch (DBException $e) {
			if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			throw new StaticSiteSlugTakenException('Slug is already taken: ' . $slug, 0, $e);
		}
		if (!$updated) {
			throw new UnprocessableEntityException('Publication of static site is already in progress');
		}

		$previous = clone $staticSite;
		$staticSite->setBuildId($buildId);
		if ($slug !== null) {
			$staticSite->setSlug($slug);
		}

		try {
			$this->buildArchive($staticSite, $pageIds);
		} catch (ServiceException $e) {
			$this->restoreStatus($previous);
			throw $e;
		}

		// TODO(Publish): Use PATCH of the publish service for static sites that were published already
		try {
			$this->generatorClient->build($staticSite, $title);
		} catch (StaticSiteGeneratorException $e) {
			$this->storeGeneratorFailure($staticSite, $title, $pageIds, $e);
			throw $e;
		}

		return $this->updateSettingsAndStatus($staticSite, $title, $pageIds, StaticSite::STATUS_PROVIDED);
	}

	/**
	 * @throws NotFoundException Collective or static site not found
	 * @throws NotPermittedException User is not allowed to edit the collective
	 * @throws DBException
	 */
	public function delete(int $collectiveId, int $id, string $userId): void {
		$this->getEditableCollective($collectiveId, $userId);

		$staticSite = $this->staticSiteMapper->findByIdAndCollectiveId($id, $collectiveId);
		// TODO(Publish): Send delete request to publish service once it provides an endpoint
		$this->staticSiteMapper->delete($staticSite);
		$this->deleteAllArchives($staticSite);
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
	 * Archives of static sites that were deleted meanwhile do not get returned. Archives of
	 * builds other than the latest one are returned as long as they exist, so a build that
	 * overlaps with a newer one can still finish.
	 *
	 * @throws NotFoundException Static site or archive of the build not found
	 * @throws DBException
	 */
	public function getArchive(string $staticSiteId, string $buildId): ISimpleFile {
		if (!Uuid::isValid($buildId)) {
			throw new NotFoundException('Invalid build ID');
		}
		try {
			$this->staticSiteMapper->findOneByStaticSiteId($staticSiteId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}

		return $this->archiver->get($staticSiteId, $buildId);
	}

	/**
	 * Called back by the external static site generator once publishing finished, identified by the unguessable
	 * `staticSiteId` rather than a logged-in user (no bearer token so far). Only applied while
	 * the build is still pending (status "provided"); late/duplicate callbacks are ignored,
	 * though this can't distinguish a very late callback from a coincidentally-pending rebuild.
	 *
	 * Status published without a valid `publish_url` is stored as failed, so a published static site
	 * always has one. The archive isn't needed anymore once the result is stored, whatever it is.
	 *
	 * @param array{publish_url?: string, error_message?: string} $data
	 *
	 * @throws NotFoundException Static site not found
	 * @throws UnprocessableEntityException Invalid status
	 * @throws DBException
	 */
	public function updateStatus(string $staticSiteId, string $status, array $data = []): void {
		if (!in_array($status, self::ALLOWED_CALLBACK_STATUSES, true)) {
			throw new UnprocessableEntityException('Invalid status: ' . $status);
		}
		try {
			$staticSite = $this->staticSiteMapper->findOneByStaticSiteId($staticSiteId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}

		if ($staticSite->getStatus() !== StaticSite::STATUS_PROVIDED) {
			$this->logger->warning('Ignoring static site status callback for status ' . $staticSite->getStatus(), ['staticSiteId' => $staticSiteId]);
			return;
		}

		if ($status === StaticSite::STATUS_PUBLISHED) {
			$publishedUrl = $data['publish_url'] ?? null;
			if (is_string($publishedUrl) && $this->isValidPublishUrl($publishedUrl)) {
				$concluded = $this->staticSiteMapper->updateStatusPublished($staticSite, $publishedUrl);
			} else {
				$this->logger->warning('Static site generator reported an invalid publish_url', $this->getLogContext($staticSite) + ['publishUrl' => $publishedUrl]);
				$concluded = $this->staticSiteMapper->updateStatusFailed($staticSite, StaticSite::ERROR_GENERATOR_INVALID_URL);
			}
		} else {
			if (isset($data['error_message'])) {
				$this->logger->warning('Static site generator failed to publish static site: ' . $data['error_message'], ['staticSiteId' => $staticSiteId]);
			}
			$concluded = $this->staticSiteMapper->updateStatusFailed($staticSite, StaticSite::ERROR_GENERATOR_FAILED);
		}

		if (!$concluded) {
			// Another callback concluded the publication or a new one started meanwhile, which needs the archive
			$this->logger->warning('Ignoring static site status callback, the static site changed meanwhile', $this->getLogContext($staticSite));
			return;
		}
		$this->deleteArchive($staticSite);
	}

	/**
	 * @param list<int> $pageIds IDs of the pages to include in the archive, incl. the collective's root page
	 *
	 * @throws UnprocessableEntityException Content can't be stored in the archive, e.g. too large or a path is too long
	 * @throws ServiceException
	 */
	private function buildArchive(StaticSite $staticSite, array $pageIds): void {
		// TODO(Publish): Consider building the archive in a queued background job once the publish service reports the build result via callback
		try {
			$files = $this->contentCollector->collect($staticSite->getCollectiveId(), $pageIds);
			$this->validateSize($files);
			$this->archiver->store($staticSite->getStaticSiteId(), $staticSite->getBuildId(), $files);
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
		$maxSize = $this->appConfig->getValueInt('collectives', ConfigLexicon::STATIC_SITE_MAX_SIZE, ConfigLexicon::STATIC_SITE_MAX_SIZE_DEFAULT);
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
	private function updateSettingsAndStatus(StaticSite $staticSite, string $title, array $pageIds, string $status, ?string $lastError = null): StaticSite {
		try {
			return $this->staticSiteMapper->updateSettingsAndStatus($staticSite, $title, $pageIds, $status, $lastError);
		} catch (NotFoundException $e) {
			$this->deleteArchive($staticSite);
			throw $e;
		}
	}

	/**
	 * Store the settings with status failed, so publishing can be retried later.
	 * The archive is only downloaded after an accepted build, so it is deleted.
	 *
	 * @throws NotFoundException Static site was deleted meanwhile
	 * @throws DBException
	 */
	private function storeGeneratorFailure(StaticSite $staticSite, string $title, array $pageIds, StaticSiteGeneratorException $e): void {
		$lastError = $e instanceof StaticSiteGeneratorUnavailableException
			? StaticSite::ERROR_GENERATOR_UNAVAILABLE
			: StaticSite::ERROR_GENERATOR_REJECTED;
		$this->updateSettingsAndStatus($staticSite, $title, $pageIds, StaticSite::STATUS_FAILED, $lastError);
		$this->deleteArchive($staticSite);
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
	 * Delete the archive of the static site's current build.
	 * Failing to delete the archive only leaves an orphaned file, so it doesn't throw.
	 */
	private function deleteArchive(StaticSite $staticSite): void {
		$buildId = $staticSite->getBuildId();
		if ($buildId === null) {
			return;
		}
		try {
			$this->archiver->delete($staticSite->getStaticSiteId(), $buildId);
		} catch (ServiceException $e) {
			$this->logger->warning('Failed to delete static site archive', $this->getLogContext($staticSite) + ['exception' => $e]);
		}
	}

	/**
	 * Failing to delete the archives only leaves orphaned files, so it doesn't throw.
	 */
	private function deleteAllArchives(StaticSite $staticSite): void {
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
	 * Only one publication per collective may run at a time, aborted ones don't count
	 *
	 * @throws StaticSitePublicationInProgressException
	 * @throws DBException
	 */
	private function assertNoPublicationInProgress(int $collectiveId): void {
		$now = $this->timeFactory->getTime();
		foreach ($this->staticSiteMapper->findByCollectiveId($collectiveId) as $staticSite) {
			if ($staticSite->isInProgress($now)) {
				throw new StaticSitePublicationInProgressException('A static site of the collective is being published');
			}
		}
	}

	/**
	 * Only creating and updating static sites is blocked, so editors can still list and delete existing ones.
	 *
	 * @throws NotPermittedException
	 */
	private function assertPublishFeatureEnabled(): void {
		if (!$this->appConfig->getValueBool('collectives', ConfigLexicon::PUBLISH_ENABLED, ConfigLexicon::PUBLISH_ENABLED_DEFAULT)) {
			throw new NotPermittedException('Publish feature is disabled');
		}
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
	 * @return ?string The validated new slug, null if it is unchanged
	 *
	 * @throws UnprocessableEntityException Invalid slug or slug of a published static site changed
	 */
	private function getChangedSlug(StaticSite $staticSite, ?string $slug): ?string {
		if ($slug === null || $slug === $staticSite->getSlug()) {
			return null;
		}
		if ($staticSite->getPublishedUrl()) {
			throw new UnprocessableEntityException('Slug of a published static site can\'t be changed');
		}
		$this->validateSlug($slug);
		return $slug;
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	private function validateSlug(string $slug): void {
		if (strlen($slug) > self::SLUG_MAX_LENGTH || !preg_match(self::SLUG_PATTERN, $slug)) {
			throw new UnprocessableEntityException('Slug must consist of 1 to ' . self::SLUG_MAX_LENGTH . ' lowercase ASCII letters and numbers, separated by single hyphens');
		}
	}

	private function isValidPublishUrl(string $url): bool {
		return strlen($url) <= self::PUBLISHED_URL_MAX_LENGTH
			&& in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
			&& (string)parse_url($url, PHP_URL_HOST) !== '';
	}

	/**
	 * Validate that all given page IDs belong to the collective and add any missing ancestor
	 * pages (incl. the collective's root page), so a subpage can never end up selected without
	 * its parent pages, keeping the hierarchical structure intact.
	 *
	 * @param list<int> $pageIds
	 *
	 * @return list<int>
	 *
	 * @throws NotFoundException If a page doesn't belong to the collective
	 * @throws NotPermittedException
	 */
	private function resolvePageIdsWithAncestors(int $collectiveId, array $pageIds, string $userId): array {
		$parentIds = [];
		foreach ($this->pageService->findAll($collectiveId, $userId) as $pageInfo) {
			$parentIds[$pageInfo->getId()] = $pageInfo->getParentId();
		}

		$unknownPageIds = array_diff($pageIds, array_keys($parentIds));
		if (!empty($unknownPageIds)) {
			throw new NotFoundException('Page(s) not found in collective: ' . implode(', ', $unknownPageIds));
		}

		$resolvedPageIds = [];
		foreach ($pageIds as $pageId) {
			// Walk up to the root page, stop early at an ancestor that was resolved before
			while ($pageId !== 0 && !isset($resolvedPageIds[$pageId])) {
				$resolvedPageIds[$pageId] = $pageId;
				$pageId = $parentIds[$pageId] ?? 0;
			}
		}

		return array_values($resolvedPageIds);
	}
}
