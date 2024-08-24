<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020802Date20231004000000 extends SimpleMigrationStep {
	private IDBConnection $connection;
	private bool $runPageModeMigration = false;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('collectives_u_settings');

		if (!$table->hasColumn('settings')) {
			$table->addColumn('settings', Types::STRING, [
				'notnull' => true,
				'default' => '{}'
			]);
			$this->runPageModeMigration = true;

			// Required to allow setting other columns without providing a value for `page_order`
			if ($table->hasColumn('page_order')) {
				$table->changeColumn('page_order', [
					'default' => 0,
				]);
			}

			return $schema;
		}

		if ($table->getColumn('settings')->getType() === Type::getType('json')) {
			// `settings` column exists and is JSON, migrate to STRING
			$table->changeColumn('settings', [
				'type' => Type::getType('string')
			]);
			return $schema;
		}

		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if ($this->runPageModeMigration) {
			$query = $this->connection->getQueryBuilder();
			$query->select(['id', 'page_order'])
				->from('collectives_u_settings');
			$result = $query->executeQuery();

			$update = $this->connection->getQueryBuilder();
			$update->update('collectives_u_settings')
				->set('settings', $update->createParameter('newSettings'))
				->where($update->expr()->eq('id', $update->createParameter('id')));

			while ($row = $result->fetch()) {
				$newSettings = json_encode(['page_order' => (int)$row['page_order']], JSON_THROW_ON_ERROR);
				$update
					->setParameter('id', (int)$row['id'], IQueryBuilder::PARAM_INT)
					->setParameter('newSettings', $newSettings, IQueryBuilder::PARAM_STR)
					->executeStatement();
			}
			$result->closeCursor();
		}
	}
}
