<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCA\Circles\Model\Member;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @method Collective insert(Collective $collective)
 * @method Collective delete(Collective $collective)
 * @method Collective findEntity(IQueryBuilder $query)
 * @method Collective update(Collective $collective)
 * @template-extends QBMapper<Collective>
 */
class CollectiveMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private CircleHelper $circleHelper,
	) {
		parent::__construct($db, 'collectives', Collective::class);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	private function findBy(IQueryBuilder $qb, ?string $userId = null, int $level = Member::LEVEL_MEMBER): ?Collective {
		try {
			$collective = $this->findEntity($qb);
			// Return any found collective if $userId is null
			if ($userId === null) {
				return $collective;
			}
			// Return member collectives with at least level `level`
			return ($this->circleHelper->hasLevel($collective->getCircleId(), $userId, $level)) ? $collective : null;
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		} catch (Exception $e) {
			throw new NotFoundException('Failed to run database query.', 0, $e);
		}
	}

	/**
	 * @throws NotFoundException
	 */
	public function findByCircleId(string $circleId, bool $includeTrash = false): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('circle_unique_id', $qb->createNamedParameter($circleId, IQueryBuilder::PARAM_STR)),
		];
		if (!$includeTrash) {
			$andX[] = $qb->expr()->isNull('trash_timestamp');
		}
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		} catch (Exception $e) {
			throw new NotFoundException('Failed to run database query.', 0, $e);
		}
	}

	public function findByCircleIds(array $circleIds, bool $includeTrash = false): array {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->in('circle_unique_id', $qb->createNamedParameter($circleIds, IQueryBuilder::PARAM_STR_ARRAY)),
		];
		if (!$includeTrash) {
			$andX[] = $qb->expr()->isNull('trash_timestamp');
		}
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		try {
			return $this->findEntities($qb);
		} catch (Exception $e) {
			throw new NotFoundException('Failed to run database query', 0, $e);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findTrashByCircleIdsAndUser(array $circleIds, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->in('circle_unique_id', $qb->createNamedParameter($circleIds, IQueryBuilder::PARAM_STR_ARRAY)),
			$qb->expr()->isNotNull('trash_timestamp'),
		];
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		try {
			$collectives = $this->findEntities($qb);
		} catch (Exception $e) {
			throw new NotFoundException('Failed to run database query', 0, $e);
		}

		return array_values(array_filter($collectives, fn (Collective $c) => $this->circleHelper->hasLevel($c->getCircleId(), $userId, Member::LEVEL_ADMIN)));
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findByIdAndUser(int $id, ?string $userId = null): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)),
			$qb->expr()->isNull('trash_timestamp'),
		];
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		return $this->findBy($qb, $userId);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findTrashByIdAndUser(int $id, string $userId): ?Collective {
		$qb = $this->db->getQueryBuilder();
		$andX = [
			$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)),
			$qb->expr()->isNotNull('trash_timestamp'),
		];
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->andX(...$andX));
		return $this->findBy($qb, $userId, Member::LEVEL_ADMIN);
	}

	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName);
		return $this->findEntities($qb);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function idToName(int $id, ?string $userId = null, bool $super = false): string {
		$collective = $this->findByIdAndUser($id, $userId);
		if ($collective === null) {
			throw new NotFoundException('Collective not found: ' . $id);
		}
		return $this->circleHelper->getCircle($collective->getCircleId(), $userId, $super)->getSanitizedName();
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function circleIdToName(string $circleId, ?string $userId = null, bool $super = false): string {
		return $this->circleHelper->getCircle($circleId, $userId, $super)->getSanitizedName();
	}

	public function trash(Collective $collective): Collective {
		$collective->setTrashTimestamp(time());
		return $this->update($collective);
	}

	public function restore(Collective $collective): Collective {
		$collective->setTrashTimestamp(null);
		return $this->update($collective);
	}
}
