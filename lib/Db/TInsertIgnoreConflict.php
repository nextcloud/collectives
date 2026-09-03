<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use InvalidArgumentException;
use OCP\AppFramework\Db\Entity;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Entity-level wrapper around IDBConnection::insertIgnoreConflict() for QBMapper
 * subclasses. Mirrors QBMapper::insertIgnoreConflict() proposed in nextcloud/server#64772
 * and can be dropped once we only support Nextcloud 36+.
 *
 * IDBConnection::insertIgnoreConflict() binds all values as strings, so only
 * entities with integer and string fields are supported.
 */
trait TInsertIgnoreConflict {
	/**
	 * Creates a new entry in the db from an entity, ignoring conflicts on
	 * unique constraints
	 *
	 * @param Entity $entity the entity that should be created
	 * @return int number of inserted rows (0 if a conflicting row already exists)
	 * @throws InvalidArgumentException if a field requires a parameter type other than integer or string
	 * @throws \OCP\DB\Exception
	 */
	public function insertIgnoreConflict(Entity $entity): int {
		$values = [];
		foreach ($entity->getUpdatedFields() as $property => $updated) {
			if ($property === 'id' && $entity->id === null) {
				continue;
			}

			$type = $this->getParameterTypeForProperty($entity, $property);
			if ($type !== IQueryBuilder::PARAM_INT && $type !== IQueryBuilder::PARAM_STR) {
				throw new InvalidArgumentException('Field ' . $property . ' of type ' . $type . ' is not supported by insertIgnoreConflict()');
			}

			$getter = 'get' . ucfirst($property);
			$values[$entity->propertyToColumn($property)] = $entity->$getter();
		}

		return $this->db->insertIgnoreConflict($this->tableName, $values);
	}
}
