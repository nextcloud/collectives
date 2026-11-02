<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040701Date20261026000000 extends SimpleMigrationStep {
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

		if (!$table->hasColumn('last_error')) {
			$table->addColumn('last_error', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => null,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('published_at')) {
			$table->addColumn('published_at', Types::BIGINT, [
				'notnull' => false,
				'default' => null,
			]);
			$changed = true;
		}

		return $changed ? $schema : null;
	}

	/**
	 * The time of the last successful publication isn't known for static sites that were
	 * published before, so the time of their last update serves as approximation.
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->connection->getQueryBuilder();
		$qb->update('collectives_st_sites')
			->set('published_at', 'updated_at')
			->where($qb->expr()->isNull('published_at'))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('status', $qb->createNamedParameter('published', IQueryBuilder::PARAM_STR)),
				$qb->expr()->isNotNull('published_url'),
			));
		$qb->executeStatement();
	}
}
