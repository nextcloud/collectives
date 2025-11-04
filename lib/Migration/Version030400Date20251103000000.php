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

class Version030400Date20251103000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('collectives_page_links')) {
			return null;
		}

		$table = $schema->createTable('collectives_page_links');
		$table->addColumn('page_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->addColumn('linked_page_id', Types::BIGINT, [
			'notnull' => true,
			'length' => 20,
		]);
		$table->setPrimaryKey(['page_id', 'linked_page_id']);

		return $schema;
	}
}
