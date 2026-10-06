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
use OCP\DB\Exception as DBException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use Psr\Log\LoggerInterface;

class StaticSiteService {
	private const TITLE_MAX_LENGTH = 255;
	private const SLUG_MAX_LENGTH = 64;
	private const SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';
	private const ALLOWED_CALLBACK_STATUSES = [
		StaticSite::STATUS_PUBLISHED,
		StaticSite::STATUS_FAILED,
	];

	public function __construct(
		private readonly StaticSiteMapper $staticSiteMapper,
		private readonly CollectiveService $collectiveService,
		private readonly PageService $pageService,
		private readonly SlugService $slugService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Create a selection of pages of a collective as a static site.
	 *
	 * Title and slug default to the collective name.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish, incl. the collective's root page
	 *
	 * @throws NotFoundException Collective or one of the pages not found
	 * @throws NotPermittedException User is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected, invalid title or slug, slug already in use
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
			return $this->staticSiteMapper->create($collectiveId, $pageIds, $title, $slug, $userId);
		} catch (DBException $e) {
			if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			throw new UnprocessableEntityException('Slug is already in use: ' . $slug, 0, $e);
		}
	}

	/**
	 * Update title and page selection of a static site and publish it again.
	 *
	 * The slug can't be changed as it is part of the public URL.
	 *
	 * @param list<int> $pageIds IDs of the pages to publish, incl. the collective's root page
	 *
	 * @throws NotFoundException Collective, static site or one of the pages not found
	 * @throws NotPermittedException User is not allowed to edit the collective
	 * @throws UnprocessableEntityException No pages selected, invalid title or publication in progress
	 * @throws DBException
	 */
	public function update(int $collectiveId, int $id, array $pageIds, string $title, string $userId): StaticSite {
		$pageIds = $this->normalizePageIds($pageIds);
		$this->getEditableCollective($collectiveId, $userId);

		$staticSite = $this->staticSiteMapper->findByIdAndCollectiveId($id, $collectiveId);
		if ($staticSite->isInProgress()) {
			throw new UnprocessableEntityException('Publication of static site is already in progress');
		}

		$title = trim($title);
		$this->validateTitle($title);
		$this->verifyPagesBelongToCollective($collectiveId, $pageIds, $userId);

		return $this->staticSiteMapper->republish($staticSite, $title, $pageIds);
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
	 * Update status of a static site export.
	 *
	 * Called back by the external service building the static site, identifying the
	 * export by its unguessable `staticSiteId` rather than a logged-in user. On success,
	 * `data.publish_url` is stored; on failure, `data.error_message` is logged.
	 *
	 * @param array{publish_url?: string, error_message?: string} $data
	 *
	 * @throws NotFoundException Static site not found
	 * @throws UnprocessableEntityException Invalid status
	 */
	public function updateStatus(string $staticSiteId, string $status, array $data = []): StaticSite {
		if (!in_array($status, self::ALLOWED_CALLBACK_STATUSES, true)) {
			throw new UnprocessableEntityException('Invalid status: ' . $status);
		}

		try {
			$staticSite = $this->staticSiteMapper->findOneByStaticSiteId($staticSiteId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}

		$staticSite = $this->staticSiteMapper->updateStatus($staticSite->getId(), $status);

		if ($status === StaticSite::STATUS_PUBLISHED && isset($data['publish_url'])) {
			$staticSite = $this->staticSiteMapper->updatePublishedUrl($staticSite->getId(), $data['publish_url']);
		}

		if ($status === StaticSite::STATUS_FAILED && isset($data['error_message'])) {
			$this->logger->warning('Static site export failed: ' . $data['error_message'], ['staticSiteId' => $staticSiteId]);
		}

		return $staticSite;
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
