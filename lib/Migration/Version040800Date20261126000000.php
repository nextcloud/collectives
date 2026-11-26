<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040800Date20261126000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('collectives_shares');
		if ($table->hasIndex('collectives_shares_coll_page')) {
			return null;
		}

		$table->addIndex(['collective_id', 'page_id'], 'collectives_shares_coll_page');
		return $schema;
	}
}
