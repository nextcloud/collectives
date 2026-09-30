<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040602Date20260908000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_static_sites')) {
			$table = $schema->createTable('collectives_static_sites');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('collective_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 6,
			]);
			$table->addColumn('static_site_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('selected_pages', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('published_url', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => null,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'pending',
			]);
			$table->addColumn('created_by', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('created', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('last_updated', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['collective_id'], 'collectives_static_site_c_id_idx');
			$table->addUniqueIndex(['static_site_id'], 'collectives_static_site_ssid_idx');
			return $schema;
		}

		return null;
	}
}
