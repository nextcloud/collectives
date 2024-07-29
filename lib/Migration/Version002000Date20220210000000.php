<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version002000Date20220210000000 extends SimpleMigrationStep {
	// private const defaultPageOrder = 1;

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		// This step is obsoleted by Version010500Date20220811000000
		/** @var ISchemaWrapper $schema */
		/*
		$schema = $schemaClosure();

		$table = $schema->getTable('collectives');
		if (!$table->hasColumn('page_order')) {
			$table->addColumn('page_order', Types::INTEGER, [
				'notnull' => true,
				'default' => self::defaultPageOrder,
			]);
			return $schema;
		}
		 */

		return null;
	}
}
