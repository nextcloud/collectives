<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCA\Collectives\Service\NotFoundException;
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
s * @method StaticSite insert(Entity $staticSite)
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
		parent::__construct($db, 'collectives_static_sites', StaticSite::class);
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
			->orderBy('created', 'DESC');
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
	 * @throws Exception
	 */
	public function create(int $collectiveId, array $pageIds, string $createdBy): StaticSite {
		$now = $this->timeFactory->getTime();

		$staticSite = new StaticSite();
		$staticSite->setCollectiveId($collectiveId);
		$staticSite->setStaticSiteId(Uuid::v4()->toRfc4122());
		$staticSite->setSelectedPageIds($pageIds);
		$staticSite->setStatus(StaticSite::STATUS_PENDING);
		$staticSite->setCreatedBy($createdBy);
		$staticSite->setCreated($now);
		$staticSite->setLastUpdated($now);

		return $this->insert($staticSite);
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
		$staticSite->setLastUpdated($this->timeFactory->getTime());
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
		$staticSite->setLastUpdated($this->timeFactory->getTime());
		return $this->update($staticSite);
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