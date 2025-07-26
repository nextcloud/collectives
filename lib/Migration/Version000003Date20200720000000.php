<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCA\Collectives\Db\Collective;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20200720000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives')) {
			$table = $schema->createTable('collectives');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('circle_unique_id', Types::STRING, [
				'notnull' => true,
				'length' => 31,
			]);
			$table->addColumn('permissions', Types::INTEGER, [
				'notnull' => true,
				// Grant full access to all member levels per default
				'default' => Collective::defaultPermissions,
			]);
			$table->addColumn('emoji', Types::STRING, [
				'notnull' => false,
				'length' => 8,
			]);
			$table->addColumn('trash_timestamp', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('page_mode', Types::INTEGER, [
				'notnull' => true,
				'default' => Collective::defaultPageMode,
			]);
			$table->addColumn('slug', Types::STRING, [
				'notnull' => false,
				'default' => false,
				'length' => 255,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['circle_unique_id'], 'collectives_circle_id_index');
			return $schema;
		}

		return null;
	}
}
