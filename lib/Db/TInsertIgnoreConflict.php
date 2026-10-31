<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Db;

use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\SnowflakeAwareEntity;

/**
 * Helper trait to insert entities while ignoring conflicts on unique constraints
 */
trait TInsertIgnoreConflict {
	/**
	 * Creates a new entry in the db from an entity, ignoring conflicts on
	 * unique constraints
	 *
	 * @param Entity $entity the entity that should be created
	 * @return int number of inserted rows (0 if a conflicting row already exists)
	 * @throws \OCP\DB\Exception
	 */
	public function insertIgnoreConflict(Entity $entity): int {
		if ($entity instanceof SnowflakeAwareEntity) {
			/** @psalm-suppress DocblockTypeContradiction */
			$entity->generateId();
		}

		$properties = $entity->getUpdatedFields();
		$values = [];
		foreach ($properties as $property => $updated) {
			if ($property === 'id' && $entity->id === null) {
				continue;
			}

			$getter = 'get' . ucfirst($property);
			$values[$entity->propertyToColumn($property)] = $entity->$getter();
		}

		return $this->db->insertIgnoreConflict($this->tableName, $values);
	}
}
