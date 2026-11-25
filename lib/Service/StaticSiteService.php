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
	// The static site generator deleted the static site
	private const CALLBACK_STATUS_DELETED = 'deleted';
	private const ALLOWED_CALLBACK_STATUSES = [
		StaticSite::STATUS_PUBLISHED,
		StaticSite::STATUS_FAILED,
		self::CALLBACK_STATUS_DELETED,
	];

	public function __construct(
		private readonly StaticSiteMapper $staticSiteMapper,
		private readonly CollectiveService $collectiveService,
		private readonly PageService $pageService,
		private readonly SlugService $slugService,
		private readonly StaticSiteContentCollector $contentCollector,
		private readonly StaticSiteArchiver $archiver,
		private readonly StaticSiteGeneratorClient $generatorClient,
		private readonly StaticSiteRemover $remover,
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
	 * Title defaults to the collective name. Slug stays empty. Theme defaults to "wiki". If the
	 * archive can't be provided, the static site is removed again. If the generator doesn't
	 * accept the build, the static site is stored with status failed, so publishing it can be retried.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish; their ancestor pages (incl. the
	 *                           collective's root page) are added automatically if missing
	 * @param ?string $theme Rendering of the website, one of StaticSite::ALLOWED_THEMES
	 *
	 * @throws NotFoundException Collective or one of the pages not found
	 * @throws NotPermittedException Publish feature is disabled or user is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected, invalid title, slug or theme, path too long
	 * @throws StaticSiteSlugTakenException Slug is already used by another static site
	 * @throws StaticSitePublicationInProgressException A static site of the collective is being published
	 * @throws StaticSiteGeneratorException Generator not configured or didn't accept the build
	 * @throws ServiceException Archive couldn't be provided
	 * @throws DBException
	 */
	public function create(int $collectiveId, array $pageIds, string $userId, ?string $title = null, ?string $slug = null, ?string $theme = null): StaticSite {
		$this->assertPublishFeatureEnabled();
		$pageIds = $this->normalizePageIds($pageIds);
		$collective = $this->getEditableCollective($collectiveId, $userId);
		$this->assertNoPublicationInProgress($collectiveId);

		$title = trim($title ?? $collective->getName());
		$slug ??= $this->generateSlug($collective->getName());
		$theme ??= StaticSite::THEME_WIKI;
		$this->validateTitle($title);
		$this->validateSlug($slug);
		$this->validateTheme($theme);
		$pageIds = $this->resolvePageIdsWithAncestors($collectiveId, $pageIds, $userId);
		$this->generatorClient->assertConfigured();

		// TODO(Publish): Also check the slug against the publish service once it provides an endpoint
		try {
			$staticSite = $this->staticSiteMapper->create($collectiveId, $pageIds, $title, $slug, $userId, $theme);
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
			$this->remover->deleteArchives($staticSite);
			throw $e;
		}

		try {
			$this->generatorClient->create($staticSite, $title, $theme);
		} catch (StaticSiteGeneratorException $e) {
			$this->storeGeneratorFailure($staticSite, $e);
			throw $e;
		}

		return $this->concludeRequest($staticSite, StaticSite::STATUS_PROVIDED);
	}

	/**
	 * Update title, page selection, slug and theme of a static site, provide its archive again and
	 * ask the static site generator to build it. A static site that never got published is
	 * created at the generator, as it only keeps static sites that got published.
	 *
	 * The slug can only be changed until the static site got published, as it is part of the
	 * public URL. The theme can be changed anytime. If the archive can't be provided, the static
	 * site and its previous archive remain unchanged. If the generator doesn't accept the build,
	 * the settings are stored with status failed.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish; their ancestor pages (incl. the
	 *                           collective's root page) are added automatically if missing
	 * @param ?string $slug New slug, null keeps the current one
	 * @param ?string $theme New rendering of the website, one of StaticSite::ALLOWED_THEMES, null keeps the current one
	 *
	 * @throws NotFoundException Collective, static site or one of the pages not found
	 * @throws NotPermittedException Publish feature is disabled or user is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected, invalid title, slug or theme, slug of a published static site changed, publication started concurrently, path too long
	 * @throws StaticSiteSlugTakenException Slug is already used by another static site
	 * @throws StaticSitePublicationInProgressException A static site of the collective is being published
	 * @throws StaticSiteGeneratorException Generator not configured or didn't accept the build
	 * @throws ServiceException Archive couldn't be provided
	 * @throws DBException
	 */
	public function update(int $collectiveId, int $id, array $pageIds, string $title, string $userId, ?string $slug = null, ?string $theme = null): StaticSite {
		$this->assertPublishFeatureEnabled();
		$pageIds = $this->normalizePageIds($pageIds);
		$this->getEditableCollective($collectiveId, $userId);

		$staticSite = $this->staticSiteMapper->findByIdAndCollectiveId($id, $collectiveId);
		$this->assertNoPublicationInProgress($collectiveId);

		$title = trim($title);
		$theme ??= $staticSite->getTheme();
		$this->validateTitle($title);
		$slug = $this->getChangedSlug($staticSite, $slug);
		$this->validateTheme($theme);
		$pageIds = $this->resolvePageIdsWithAncestors($collectiveId, $pageIds, $userId);
		$this->generatorClient->assertConfigured();

		$buildId = Uuid::v7()->toRfc4122();
		// TODO(Publish): Also check the slug against the publish service once it provides an endpoint
		try {
			$updated = $this->staticSiteMapper->startBuild($staticSite, $buildId, $title, $theme, $pageIds, $slug);
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
		$staticSite->setTitle($title);
		$staticSite->setTheme($theme);
		$staticSite->setSelectedPageIds($pageIds);
		if ($slug !== null) {
			$staticSite->setSlug($slug);
		}

		try {
			$this->buildArchive($staticSite, $pageIds);
		} catch (ServiceException $e) {
			$this->restoreStatus($previous);
			// The archive may have been stored partially
			$this->deleteArchive($staticSite, $buildId);
			throw $e;
		}

		try {
			if ($staticSite->isPublished()) {
				$this->generatorClient->update($staticSite, $title, $theme);
			} else {
				$this->generatorClient->create($staticSite, $title, $theme);
			}
		} catch (StaticSiteGeneratorException $e) {
			$this->storeGeneratorFailure($staticSite, $e);
			throw $e;
		}

		return $this->concludeRequest($staticSite, StaticSite::STATUS_PROVIDED);
	}

	/**
	 * Delete a static site, also at the static site generator if it got published.
	 * See StaticSiteRemover::remove().
	 *
	 * Not while a static site of the collective is being published or deleted, as only one
	 * request per collective may run at a time.
	 *
	 * @return ?StaticSite Null if the static site got deleted, otherwise the static site being deleted,
	 *                     is loaded again from the database to include its new status and update time
	 *
	 * @throws NotFoundException Collective or static site not found
	 * @throws NotPermittedException User is not allowed to edit the collective
	 * @throws StaticSitePublicationInProgressException A static site of the collective is being published or deleted
	 * @throws UnprocessableEntityException Static site was changed meanwhile
	 * @throws StaticSiteGeneratorException Generator not configured, unavailable or didn't accept the request
	 * @throws DBException
	 */
	public function delete(int $collectiveId, int $id, string $userId): ?StaticSite {
		$this->getEditableCollective($collectiveId, $userId);

		$staticSite = $this->staticSiteMapper->findByIdAndCollectiveId($id, $collectiveId);
		$this->assertNoPublicationInProgress($collectiveId);
		if ($this->remover->remove($staticSite)) {
			return null;
		}

		try {
			return $this->staticSiteMapper->find($staticSite->getId());
		} catch (DoesNotExistException) {
			// The status callback of the deletion arrived already
			return null;
		}
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
	 * Only the archive of the current build is provided, so a build that was replaced by a newer
	 * one can't put outdated content online anymore, unless it downloaded its archive before.
	 *
	 * @throws StaticSiteBuildNotFoundException Build isn't the current one or its archive is gone
	 * @throws NotFoundException Static site not found
	 * @throws DBException
	 */
	public function getArchive(string $staticSiteId, string $buildId): ISimpleFile {
		$staticSite = $this->findByStaticSiteId($staticSiteId);
		if ($buildId !== $staticSite->getBuildId()) {
			throw new StaticSiteBuildNotFoundException('Build is not the current one of the static site');
		}

		try {
			return $this->archiver->get($staticSiteId, $buildId);
		} catch (NotFoundException $e) {
			throw new StaticSiteBuildNotFoundException('Archive of the build not found', 0, $e);
		}
	}

	/**
	 * Called back by the external static site generator once a build finished. Not authenticated,
	 * the callback URL with the unguessable static site and build IDs authorizes it.
	 *
	 * Only the current build changes the status, whatever the status is otherwise: the callback
	 * may arrive before the request to the generator is concluded or after the publication was
	 * considered aborted. A build that got published already isn't changed anymore.
	 *
	 * Callbacks of older builds are only logged. Their settings aren't known anymore, so a late
	 * successful older build can't be stored as publication.
	 *
	 * A deletion has its own build ID. Its status deleted removes the static site, status failed
	 * keeps it online. See StaticSiteRemover::remove().
	 *
	 * Status published without a valid `publish_url` is stored as failed, so a published static site
	 * always has one. The archive of the build isn't needed anymore once the build finished.
	 *
	 * @param string $status "published", "failed" or "deleted"
	 * @param array{publish_url?: string, error_message?: string, build_id?: string} $data `build_id` must match the build of the URL if given
	 *
	 * @throws StaticSiteBuildNotFoundException Build is neither the current nor an older one
	 * @throws NotFoundException Static site not found
	 * @throws UnprocessableEntityException Invalid status
	 * @throws DBException
	 */
	public function updateStatus(string $staticSiteId, string $buildId, string $status, array $data = []): void {
		if (!in_array($status, self::ALLOWED_CALLBACK_STATUSES, true)) {
			throw new UnprocessableEntityException('Invalid status: ' . $status);
		}
		$staticSite = $this->findByStaticSiteId($staticSiteId);

		$logContext = $this->getLogContext($staticSite) + ['callbackBuildId' => $buildId, 'callbackStatus' => $status];
		if (isset($data['build_id']) && $data['build_id'] !== $buildId) {
			$this->logger->warning('Ignoring static site status callback, its build_id does not match the callback URL', $logContext + ['bodyBuildId' => $data['build_id']]);
			return;
		}
		if ($buildId !== $staticSite->getBuildId()) {
			$this->ignoreStatusOfOlderBuild($staticSite, $buildId, $status, $logContext);
			return;
		}
		if ($staticSite->getStatus() === StaticSite::STATUS_DELETING) {
			$this->updateDeletionStatus($staticSite, $status, $data, $logContext);
			return;
		}
		if ($status === self::CALLBACK_STATUS_DELETED) {
			$this->logger->warning('Ignoring static site status callback, the static site is not being deleted', $logContext);
			return;
		}

		if ($status === StaticSite::STATUS_PUBLISHED) {
			$publishedUrl = $data['publish_url'] ?? null;
			if (is_string($publishedUrl) && $this->isValidPublishUrl($publishedUrl)) {
				$concluded = $this->staticSiteMapper->updateStatusPublished($staticSite, $publishedUrl);
			} else {
				$this->logger->warning('Static site generator reported an invalid publish_url', $logContext + ['publishUrl' => $publishedUrl]);
				$concluded = $this->staticSiteMapper->updateStatusFailed($staticSite, StaticSite::ERROR_GENERATOR_INVALID_URL);
			}
		} else {
			if (isset($data['error_message'])) {
				$this->logger->warning('Static site generator failed to publish static site: ' . $data['error_message'], $logContext);
			}
			$concluded = $this->staticSiteMapper->updateStatusFailed($staticSite, StaticSite::ERROR_GENERATOR_FAILED);
		}

		if (!$concluded) {
			$this->logger->warning('Ignoring static site status callback, the build got published already or another build started meanwhile', $logContext);
		}
		$this->deleteArchive($staticSite, $buildId);
	}

	/**
	 * A static site that failed to be deleted stays online, so deleting it can be retried.
	 *
	 * @param array{publish_url?: string, error_message?: string, build_id?: string} $data
	 *
	 * @throws DBException
	 */
	private function updateDeletionStatus(StaticSite $staticSite, string $status, array $data, array $logContext): void {
		if ($status === self::CALLBACK_STATUS_DELETED) {
			$this->remover->removeDeleted($staticSite);
			return;
		}
		if ($status !== StaticSite::STATUS_FAILED) {
			$this->logger->warning('Ignoring static site status callback, the static site is being deleted', $logContext);
			return;
		}

		if (isset($data['error_message'])) {
			$this->logger->warning('Static site generator failed to delete static site: ' . $data['error_message'], $logContext);
		}
		if (!$this->staticSiteMapper->updateStatusFailed($staticSite, StaticSite::ERROR_GENERATOR_DELETE_FAILED)) {
			$this->logger->warning('Ignoring static site status callback, another request started meanwhile', $logContext);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws DBException
	 */
	private function findByStaticSiteId(string $staticSiteId): StaticSite {
		try {
			return $this->staticSiteMapper->findOneByStaticSiteId($staticSiteId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}
	}

	/**
	 * Builds are UUIDv7, so a build ID sorting before the current one belongs to an older build.
	 * Its archive was replaced by the one of the current build already.
	 *
	 * @throws StaticSiteBuildNotFoundException Build isn't older than the current one
	 */
	private function ignoreStatusOfOlderBuild(StaticSite $staticSite, string $buildId, string $status, array $logContext): void {
		if (!Uuid::isValid($buildId) || strcmp($buildId, $staticSite->getBuildId() ?? '') >= 0) {
			throw new StaticSiteBuildNotFoundException('Build of static site not found');
		}

		$duplicate = $buildId === $staticSite->getPublishedBuildId();
		if ($status === StaticSite::STATUS_PUBLISHED && !$duplicate) {
			// Known limitation: the settings of the build aren't known, so the publication can't be stored
			$this->logger->warning('Static site generator published a build that is not the current one anymore', $logContext);
		} else {
			$this->logger->info('Ignoring static site status callback of a build that is not the current one', $logContext);
		}
	}

	/**
	 * @param list<int> $pageIds IDs of the pages to include in the archive, incl. the collective's root page
	 *
	 * @throws UnprocessableEntityException Content can't be stored in the archive, e.g. too large or a path is too long
	 * @throws ServiceException
	 */
	private function buildArchive(StaticSite $staticSite, array $pageIds): void {
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
		$this->deleteArchivesOfOtherBuilds($staticSite);
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
	 * Store whether the static site generator accepted the build (status provided) or not
	 * (status failed) and return the static site as stored.
	 *
	 * Title and page selection were stored when the build started, so they are kept even if
	 * the build failed, and the user can retry it without losing the latest changes.
	 *
	 * If the status callback of the build arrived before this, its result is kept.
	 *
	 * @param ?string $lastError One of the StaticSite::ERROR_* reasons if the status is failed
	 *
	 * @throws NotFoundException Static site was deleted meanwhile
	 * @throws DBException
	 */
	private function concludeRequest(StaticSite $staticSite, string $status, ?string $lastError = null): StaticSite {
		$this->staticSiteMapper->updateRequestStatus($staticSite, $status, $lastError);
		try {
			return $this->staticSiteMapper->find($staticSite->getId());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			$this->remover->deleteArchives($staticSite);
			throw new NotFoundException('Static site not found', 0, $e);
		}
	}

	/**
	 * Store status failed, so publishing can be retried later.
	 *
	 * A build the generator didn't accept never downloads its archive, so it is deleted. If the
	 * generator didn't answer after the request was sent, it may have accepted the build anyway,
	 * so the archive is kept.
	 *
	 * @throws NotFoundException Static site was deleted meanwhile
	 * @throws DBException
	 */
	private function storeGeneratorFailure(StaticSite $staticSite, StaticSiteGeneratorException $e): void {
		$lastError = match (true) {
			$e instanceof StaticSiteGeneratorUnavailableException => StaticSite::ERROR_GENERATOR_UNAVAILABLE,
			$e instanceof StaticSiteGeneratorBusyException => StaticSite::ERROR_GENERATOR_BUSY,
			default => StaticSite::ERROR_GENERATOR_REJECTED,
		};
		$this->concludeRequest($staticSite, StaticSite::STATUS_FAILED, $lastError);
		if (!$e instanceof StaticSiteGeneratorTimeoutException) {
			$this->deleteArchive($staticSite, $staticSite->getBuildId());
		}
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
	 * Failing to delete the archive only leaves an orphaned file, so it doesn't throw.
	 */
	private function deleteArchive(StaticSite $staticSite, ?string $buildId): void {
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
	 * Only the archive of the current build is provided, so the others aren't needed anymore.
	 * Failing to delete them only leaves orphaned files, so it doesn't throw.
	 */
	private function deleteArchivesOfOtherBuilds(StaticSite $staticSite): void {
		try {
			$this->archiver->deleteAllExcept($staticSite->getStaticSiteId(), $staticSite->getBuildId());
		} catch (ServiceException $e) {
			$this->logger->warning('Failed to delete archives of previous static site builds', $this->getLogContext($staticSite) + ['exception' => $e]);
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
	 * Only one request to the static site generator per collective may run at a time, a build or a
	 * deletion. Aborted ones don't count.
	 *
	 * @throws StaticSitePublicationInProgressException
	 * @throws DBException
	 */
	private function assertNoPublicationInProgress(int $collectiveId): void {
		$now = $this->timeFactory->getTime();
		foreach ($this->staticSiteMapper->findByCollectiveId($collectiveId) as $staticSite) {
			if ($staticSite->isInProgress($now)) {
				throw new StaticSitePublicationInProgressException('A static site of the collective is being published or deleted');
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
	 * @throws UnprocessableEntityException
	 */
	private function validateTheme(string $theme): void {
		if (!in_array($theme, StaticSite::ALLOWED_THEMES, true)) {
			throw new UnprocessableEntityException('Theme must be one of: ' . implode(', ', StaticSite::ALLOWED_THEMES));
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
		if ($staticSite->isPublished()) {
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

	// TODO(Publish): The status callback isn't authenticated, so anyone knowing its URL can set any publish_url.
	// Only allow the configured website url once it is configurable in the admin settings.
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
