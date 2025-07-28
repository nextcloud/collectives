<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCA\Collectives\Service\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<Tag> */
class TagMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'collectives_tags', Tag::class);
	}

	/**
	 * @throws NotFoundException
	 */
	public function find(int $collectiveId, int $id): Tag {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Tag not found for collective: ' . $id, 0, $e);
		}
	}

	public function findByName(int $collectiveId, string $name): ?Tag {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)))
			->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($name)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	public function findAll(int $collectiveId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)));

		return $this->findEntities($qb);
	}

	public function deleteByCollectiveId(int $collectiveId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('collective_id', $qb->createNamedParameter($collectiveId)));

		return $qb->executeStatement();
	}
}
