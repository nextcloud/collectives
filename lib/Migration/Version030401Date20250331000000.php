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

class Version030401Date20250331000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('collectives_pages')) {
			$table = $schema->getTable('collectives_pages');
			$changed = false;

			if (!$table->hasColumn('collective_id')) {
				$table->addColumn('collective_id', Types::BIGINT, [
					'notnull' => false,
				]);
				$changed = true;
			}

			if (!$table->hasIndex('collectives_pages_c_id_idx')) {
				$table->addIndex(['collective_id'], 'collectives_pages_c_id_idx');
				$changed = true;
			}

			if ($changed) {
				return $schema;
			}
		}

		return null;
	}
}
