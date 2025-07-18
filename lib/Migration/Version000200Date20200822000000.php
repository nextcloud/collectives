<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000200Date20200822000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_pages')) {
			$table = $schema->createTable('collectives_pages');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('last_user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('emoji', Types::STRING, [
				'notnull' => false,
				'length' => 8,
			]);
			$table->addColumn('trash_timestamp', Types::INTEGER, [
				'notnull' => false,
			]);
			$table->addColumn('subpage_order', Types::TEXT, [
				'notnull' => false,
				'length' => null,
			]);
			$table->addColumn('full_width', Types::BOOLEAN, [
				'notnull' => false,
				'default' => false,
			]);
			$table->addColumn('slug', Types::STRING, [
				'notnull' => false,
				'default' => false,
				'length' => 255,
			]);
			$table->addColumn('tags', Types::TEXT, [
				'notnull' => false,
				'length' => null,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['file_id'], 'collectives_pages_file_index');
			return $schema;
		}

		return null;
	}
}
