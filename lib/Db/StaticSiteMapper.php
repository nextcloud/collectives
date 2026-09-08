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
			->orderBy('created_at', 'DESC');
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
	public function create(int $collectiveId, array $pageIds, string $title, string $slug, string $createdBy): StaticSite {
		$now = $this->timeFactory->getTime();

		$staticSite = new StaticSite();
		$staticSite->setCollectiveId($collectiveId);
		$staticSite->setStaticSiteId(Uuid::v7()->toRfc4122());
		$staticSite->setTitle($title);
		$staticSite->setSlug($slug);
		$staticSite->setSelectedPageIds($pageIds);
		$staticSite->setStatus(StaticSite::STATUS_PENDING);
		$staticSite->setCreatedBy($createdBy);
		$staticSite->setCreatedAt($now);
		$staticSite->setUpdatedAt($now);

		return $this->insert($staticSite);
	}

	/**
	 * Set the status to pending, unless the static site was changed since it was read.
	 *
	 * Check and update happen in a single query, so concurrent requests can't start
	 * a publication twice.
	 *
	 * @return bool False if the static site was changed meanwhile
	 *
	 * @throws Exception
	 */
	public function startPublication(StaticSite $staticSite): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter(StaticSite::STATUS_PENDING, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter($this->timeFactory->getTime(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($staticSite->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($staticSite->getStatus(), IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('updated_at', $qb->createNamedParameter($staticSite->getUpdatedAt(), IQueryBuilder::PARAM_INT)));

		return $qb->executeStatement() > 0;
	}

	/**
	 * Store title and page selection of a successfully provided publication.
	 *
	 * @param list<int> $pageIds
	 *
	 * @throws NotFoundException
	 * @throws UnprocessableEntityException
	 * @throws Exception
	 */
	public function finishPublication(StaticSite $staticSite, string $title, array $pageIds): StaticSite {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('title', $qb->createNamedParameter($title, IQueryBuilder::PARAM_STR))
			->set('selected_pages', $qb->createNamedParameter(StaticSite::encodePageIds($pageIds), IQueryBuilder::PARAM_STR))
			->set('status', $qb->createNamedParameter(StaticSite::STATUS_PROVIDED, IQueryBuilder::PARAM_STR))
			->set('updated_at', $qb->createNamedParameter($this->timeFactory->getTime(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($staticSite->getId(), IQueryBuilder::PARAM_INT)));

		if ($qb->executeStatement() === 0) {
			throw new NotFoundException('Static site not found');
		}

		try {
			return $this->find($staticSite->getId());
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function updateStatus(int $id, string $status): StaticSite {
		try {
			$staticSite = $this->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}

		$staticSite->setStatus($status);
		$staticSite->setUpdatedAt($this->timeFactory->getTime());
		return $this->update($staticSite);
	}

	/**
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function updatePublishedUrl(int $id, string $publishedUrl): StaticSite {
		try {
			$staticSite = $this->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Static site not found', 0, $e);
		}

		$staticSite->setPublishedUrl($publishedUrl);
		$staticSite->setUpdatedAt($this->timeFactory->getTime());
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
