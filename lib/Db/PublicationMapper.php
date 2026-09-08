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
 * @method Publication insert(Entity $publication)
 * @method Publication update(Entity $publication)
 * @method Publication delete(Entity $publication)
 * @method Publication findEntity(IQueryBuilder $query)
 * @template-extends QBMapper<Publication>
 */
class PublicationMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private readonly ITimeFactory $timeFactory,
	) {
		parent::__construct($db, 'collectives_publications', Publication::class);
	}

	/**
	 * @return Publication[]
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
	public function findOneByStaticSiteId(string $staticSiteId): Publication {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('static_site_id', $qb->createNamedParameter($staticSiteId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntity($qb);
	}

	/**
	 * @param int[] $pageIds
	 *
	 * @throws Exception
	 */
	public function create(int $collectiveId, array $pageIds, string $createdBy): Publication {
		$now = $this->timeFactory->getTime();

		$publication = new Publication();
		$publication->setCollectiveId($collectiveId);
		$publication->setStaticSiteId(Uuid::v4()->toRfc4122());
		$publication->setSelectedPageIds($pageIds);
		$publication->setStatus(Publication::STATUS_PENDING);
		$publication->setCreatedBy($createdBy);
		$publication->setCreated($now);
		$publication->setLastUpdated($now);

		return $this->insert($publication);
	}

	/**
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function updateStatus(int $id, string $status): Publication {
		try {
			$publication = $this->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Publication not found', 0, $e);
		}

		$publication->setStatus($status);
		$publication->setLastUpdated($this->timeFactory->getTime());
		return $this->update($publication);
	}

	/**
	 * @throws NotFoundException
	 * @throws Exception
	 */
	public function updatePublishedUrl(int $id, string $publishedUrl): Publication {
		try {
			$publication = $this->find($id);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Publication not found', 0, $e);
		}

		$publication->setPublishedUrl($publishedUrl);
		$publication->setLastUpdated($this->timeFactory->getTime());
		return $this->update($publication);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function find(int $id): Publication {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}
}
