<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Symfony\Component\Uid\Uuid;

/**
 * @method StaticSite insert(Entity $staticSite)
 * @method StaticSite update(Entity $staticSite)
 * @method StaticSite delete(Entity $staticSite)
 * @method StaticSite findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<StaticSite>
 */
class StaticSiteMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private readonly ITimeFactory $timeFactory,
	) {
		parent::__construct($db, 'collectives_st_sites', StaticSite::class);
	}

	/**
	 * @return StaticSite[]
	 */
	public function findByCollectiveId(int $collectiveId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Orphaned static sites: their collective doesn't exist anymore, e.g. as deleting them at the
	 * static site generator failed when the collective got purged.
	 *
	 * @return StaticSite[]
	 * @throws Exception
	 */
	public function findAllOrphaned(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.*')
			->from($this->tableName, 's')
			->leftJoin('s', 'collectives', 'c', $qb->expr()->eq('s.collective_id', 'c.id'))
			->where($qb->expr()->isNull('c.id'));
		return $this->findEntities($qb);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function findOneByStaticSiteId(string $staticSiteId): StaticSite {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('static_site_id', $qb->createNamedParameter($staticSiteId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @param list<int> $pageIds
	 *
	 * @throws UnprocessableEntityException
	 * @throws Exception
	 */
	public function create(int $collectiveId, array $pageIds, string $title, string $slug, string $createdBy, string $theme = StaticSite::THEME_WIKI): StaticSite {
		$now = $this->timeFactory->getTime();

		$staticSite = new StaticSite();
		$staticSite->setCollectiveId($collectiveId);
		$staticSite->setStaticSiteId(Uuid::v7()->toRfc4122());
		$staticSite->setBuildId(Uuid::v7()->toRfc4122());
		$staticSite->setTitle($title);
		$staticSite->setSlug($slug);
		$staticSite->setTheme($theme);
		$staticSite->setSelectedPageIds($pageIds);
		$staticSite->setStatus(StaticSite::STATUS_PENDING);
		$staticSite->setCreatedBy($createdBy);
		$staticSite->setCreatedAt($now);
		$staticSite->setUpdatedAt($now);

		return $this->insert($staticSite);
	}

	/**
	 * Start a publication attempt with the given build and settings: set status pending and clear
	 * the last error, unless the static site was changed since it was read.
	 *
	 * Check and update happen in a single query, so of concurrent requests only one can
	 * start a publication.
	 *
	 * @param list<int> $pageIds
	 * @param ?string $slug New slug, null keeps the current one
	 * @return bool False if the static site was changed meanwhile
	 *
	 * @throws UnprocessableEntityException
	 * @throws Exception Also if the slug is already used by another static site
	 */
	public function startBuild(StaticSite $staticSite, string $buildId, string $title, string $theme, array $pageIds, ?string $slug = null): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter(StaticSite::STATUS_PENDING, IQueryBuilder::PARAM_STR))
			->set('build_id', $qb->createNamedParameter($buildId, IQueryBuilder::PARAM_STR))
			->set('title', $qb->createNamedParameter($title, IQueryBuilder::PARAM_STR))
			->set('theme', $qb->createNamedParameter($theme, IQueryBuilder::PARAM_STR))
			->set('selected_pages', $qb->createNamedParameter(StaticSite::encodePageIds($pageIds), IQueryBuilder::PARAM_STR))
			->set('last_error', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('updated_at', $qb->createNamedParameter($this->timeFactory->getTime(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($staticSite->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($staticSite->getStatus(), IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('updated_at', $qb->createNamedParameter($staticSite->getUpdatedAt(), IQueryBuilder::PARAM_INT)));
		if ($slug !== null) {
			$qb->set('slug', $qb->createNamedParameter($slug, IQueryBuilder::PARAM_STR));
		}

		return $qb->executeStatement() > 0;
	}

	/**
	 * Start deleting a published static site with the given build ID. It identifies the deletion
	 * like a build towards the static site generator: set status deleting and clear
	 * the last error, unless the static site was changed since it was read.
	 *
	 * @return bool False if the static site was changed meanwhile
	 *
	 * @throws Exception
	 */
	public function startDeletion(StaticSite $staticSite, string $buildId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter(StaticSite::STATUS_DELETING, IQueryBuilder::PARAM_STR))
			->set('build_id', $qb->createNamedParameter($buildId, IQueryBuilder::PARAM_STR))
			->set('last_error', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('updated_at', $qb->createNamedParameter($this->timeFactory->getTime(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($staticSite->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($staticSite->getStatus(), IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('updated_at', $qb->createNamedParameter($staticSite->getUpdatedAt(), IQueryBuilder::PARAM_INT)));

		return $qb->executeStatement() > 0;
	}

	/**
	 * Delete the static site, unless another request started since it was read.
	 *
	 * @return bool False if another request started or the static site was deleted already
	 *
	 * @throws Exception
	 */
	public function deleteIfCurrentBuild(StaticSite $staticSite): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($staticSite->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('build_id', $qb->createNamedParameter($staticSite->getBuildId(), IQueryBuilder::PARAM_STR)));

		return $qb->executeStatement() > 0;
	}

	/**
	 * Store the result of the request to the static site generator, unless its status
	 * callback concluded the build already or another build started meanwhile.
	 *
	 * @param ?string $lastError One of the StaticSite::ERROR_* reasons if the status is failed
	 * @return bool False if the build isn't pending anymore or the static site was deleted
	 *
	 * @throws Exception
	 */
	public function updateRequestStatus(StaticSite $staticSite, string $status, ?string $lastError = null): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR))
			->set('last_error', $qb->createNamedParameter($lastError, $lastError === null ? IQueryBuilder::PARAM_NULL : IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter($this->timeFactory->getTime(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($staticSite->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('build_id', $qb->createNamedParameter($staticSite->getBuildId(), IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(StaticSite::STATUS_PENDING, IQueryBuilder::PARAM_STR)));

		return $qb->executeStatement() > 0;
	}

	/**
	 * Store the successful publication of the current build reported by the static site generator,
	 * together with the title and page selection it got published with. See updateIfCurrentBuildUnpublished().
	 *
	 * @return bool False if another build started, the build got published already or the static site was deleted
	 *
	 * @throws Exception
	 */
	public function updateStatusPublished(StaticSite $staticSite, string $publishedUrl): bool {
		$now = $this->timeFactory->getTime();
		$qb = $this->updateIfCurrentBuildUnpublished($staticSite);
		$qb->set('status', $qb->createNamedParameter(StaticSite::STATUS_PUBLISHED, IQueryBuilder::PARAM_STR))
			->set('published_build_id', $qb->createNamedParameter($staticSite->getBuildId(), IQueryBuilder::PARAM_STR))
			->set('published_title', $qb->createNamedParameter($staticSite->getTitle(), IQueryBuilder::PARAM_STR))
			->set('published_selected_pages', $qb->createNamedParameter($staticSite->getSelectedPages(), IQueryBuilder::PARAM_STR))
			->set('published_url', $qb->createNamedParameter($publishedUrl, IQueryBuilder::PARAM_STR))
			->set('published_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->set('last_error', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT));

		return $qb->executeStatement() > 0;
	}

	/**
	 * Store the failed publication of the current build reported by the static site generator.
	 * The previous publication remains. See updateIfCurrentBuildUnpublished().
	 *
	 * @param string $lastError One of the StaticSite::ERROR_* reasons
	 *
	 * @return bool False if another build started, the build got published already or the static site was deleted
	 *
	 * @throws Exception
	 */
	public function updateStatusFailed(StaticSite $staticSite, string $lastError): bool {
		$qb = $this->updateIfCurrentBuildUnpublished($staticSite);
		$qb->set('status', $qb->createNamedParameter(StaticSite::STATUS_FAILED, IQueryBuilder::PARAM_STR))
			->set('last_error', $qb->createNamedParameter($lastError, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter($this->timeFactory->getTime(), IQueryBuilder::PARAM_INT));

		return $qb->executeStatement() > 0;
	}

	/**
	 * Update query for a static site whose build is still the current one and not published yet,
	 * whatever its status otherwise, as the callback may arrive before the request is concluded
	 * or after the publication was considered aborted.
	 *
	 * Check and update happen in a single query, so of concurrent callbacks only one can
	 * conclude the build, and none overwrites a build started meanwhile. The title and page
	 * selection only change together with the build, so they belong to it.
	 */
	private function updateIfCurrentBuildUnpublished(StaticSite $staticSite): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($staticSite->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('build_id', $qb->createNamedParameter($staticSite->getBuildId(), IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter(StaticSite::STATUS_PUBLISHED, IQueryBuilder::PARAM_STR)));
		return $qb;
	}

	/**
	 * Reset build, title, page selection, slug, status, last error and update time to the values of the given static site as it was read before.
	 *
	 * Keeping the update time of an aborted publication allows the user to start the publish process again right away.
	 *
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function restoreStatus(StaticSite $previous): StaticSite {
		try {
			$staticSite = $this->find($previous->getId());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}

		$staticSite->setSlug($previous->getSlug());
		$staticSite->setBuildId($previous->getBuildId());
		$staticSite->setTitle($previous->getTitle());
		$staticSite->setTheme($previous->getTheme());
		$staticSite->setSelectedPages($previous->getSelectedPages());
		$staticSite->setStatus($previous->getStatus());
		$staticSite->setLastError($previous->getLastError());
		$staticSite->setUpdatedAt($previous->getUpdatedAt());
		return $this->update($staticSite);
	}

	/**
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function findByIdAndCollectiveId(int $id, int $collectiveId): StaticSite {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function find(int $id): StaticSite {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}
}
