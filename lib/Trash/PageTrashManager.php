<?php

namespace OCA\Collectives\Trash;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PageTrashManager {
	private IDBConnection $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param int[] $collectiveIds
	 *
	 * @return array
	 */
	public function listTrashForCollectives(array $collectiveIds): array {
		$query = $this->connection->getQueryBuilder();
		$query->select(['trash_id', 'name', 'deleted_time', 'original_location', 'collective_id', 'file_id'])
			->from('collectives_page_trash')
			->orderBy('deleted_time')
			->where($query->expr()->in('collective_id', $query->createNamedParameter($collectiveIds, IQueryBuilder::PARAM_INT_ARRAY)));
		return $query->executeQuery()->fetchAll();
	}

	/**
	 * @param int    $collectiveId
	 * @param string $name
	 * @param int    $deletedTime
	 * @param string $originalLocation
	 * @param int    $fileId
	 */
	public function addTrashItem(int $collectiveId, string $name, int $deletedTime, string $originalLocation, int $fileId): void {
		$query = $this->connection->getQueryBuilder();
		$query->insert('collectives_page_trash')
			->values([
				'collective_id' => $query->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT),
				'name' => $query->createNamedParameter($name),
				'deleted_time' => $query->createNamedParameter($deletedTime, IQueryBuilder::PARAM_INT),
				'original_location' => $query->createNamedParameter($originalLocation),
				'file_id' => $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)
			]);
		$query->executeStatement();
	}

	/**
	 * @param int $fileId
	 *
	 * @return array|null
	 */
	public function getTrashItemByFileId(int $fileId): ?array {
		$query = $this->connection->getQueryBuilder();
		$query->select(['trash_id', 'name', 'deleted_time', 'original_location', 'collective_id'])
			->from('collectives_page_trash')
			->where($query->expr()->eq('file_id', $query->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)));
		return $query->executeQuery()->fetch() ?: null;
	}

	/**
	 * @param int    $collectiveId
	 * @param string $name
	 * @param int    $deletedTime
	 */
	public function removeItem(int $collectiveId, string $name, int $deletedTime): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('collectives_page_trash')
			->where($query->expr()->eq('collective_id', $query->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('name', $query->createNamedParameter($name)))
			->andWhere($query->expr()->eq('deleted_time', $query->createNamedParameter($deletedTime, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
	}

	/**
	 * @param int $collectiveId
	 */
	public function emptyTrash(int $collectiveId): void {
		$query = $this->connection->getQueryBuilder();
		$query->delete('collectives_page_trash')
			->where($query->expr()->eq('collective_id', $query->createNamedParameter($collectiveId, IQueryBuilder::PARAM_INT)));
		$query->executeStatement();
	}
}
