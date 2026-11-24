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

class Version040800Date20261124000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_st_sites')) {
			return null;
		}

		$table = $schema->getTable('collectives_st_sites');
		if ($table->hasColumn('theme')) {
			return null;
		}

		$table->addColumn('theme', Types::STRING, [
			'notnull' => true,
			'length' => 32,
			// Keep in sync with OCA\Collectives\Db\StaticSite::THEME_WIKI — migrations must not
			// depend on live entity classes, which can change independently of past migrations.
			'default' => 'wiki',
		]);

		return $schema;
	}
}
