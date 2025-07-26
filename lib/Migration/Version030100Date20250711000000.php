<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030100Date20250711000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('collectives_tags')) {
			return null;
		}

		$table = $schema->createTable('collectives_tags');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('collective_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('name', Types::STRING, [
			'notnull' => true,
			'length' => 250,
		]);
		$table->addColumn('color', Types::STRING, [
			'notnull' => true,
			'length' => 6,
		]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['name', 'collective_id'], 'c_tags_unique_idx');

		return $schema;
	}
}
