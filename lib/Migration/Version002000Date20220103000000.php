<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\Constants;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version002000Date20220103000000 extends SimpleMigrationStep {
	/** @var int */
	private const defaultPermissions
		= Constants::PERMISSION_ALL * 100 // Moderator
		+ Constants::PERMISSION_ALL;        // Member

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('collectives');
		if (!$table->hasColumn('permissions')) {
			$table->addColumn('permissions', Types::INTEGER, [
				'notnull' => true,
				// Grant full access to all member levels per default
				'default' => self::defaultPermissions,
			]);
			return $schema;
		}

		return null;
	}
}
