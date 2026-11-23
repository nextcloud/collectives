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
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040800Date20261123000000 extends SimpleMigrationStep {
	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_st_sites')) {
			return null;
		}

		$table = $schema->getTable('collectives_st_sites');
		$changed = false;

		foreach (['build_id', 'published_build_id'] as $column) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, Types::STRING, [
					'notnull' => false,
					'length' => 36,
					'default' => null,
				]);
				$changed = true;
			}
		}

		if (!$table->hasColumn('published_title')) {
			$table->addColumn('published_title', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => null,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('published_selected_pages')) {
			$table->addColumn('published_selected_pages', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
			$changed = true;
		}

		return $changed ? $schema : null;
	}

	/**
	 * For static sites published before, the published settings aren't known.
	 * Their current settings serve as approximation.
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_st_sites')) {
			return;
		}

		$qb = $this->connection->getQueryBuilder();
		$qb->update('collectives_st_sites')
			->set('published_title', 'title')
			->set('published_selected_pages', 'selected_pages')
			->where($qb->expr()->isNotNull('published_at'))
			->andWhere($qb->expr()->isNull('published_title'));
		$qb->executeStatement();
	}
}
