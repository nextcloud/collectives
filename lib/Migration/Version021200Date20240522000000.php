<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version021200Date20240522000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_sessions')) {
			$table = $schema->createTable('collectives_sessions');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('collective_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('last_contact', Types::BIGINT, [
				'notnull' => true,
				'length' => 6,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['token'], 'collectives_session_token_idx');
			$table->addIndex(['collective_id'], 'collectives_session_c_id_idx');
			$table->addIndex(['last_contact'], 'collectives_session_lc_idx');
			return $schema;
		}

		return null;
	}
}
