<?php

declare(strict_types=1);

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version001600Date20211025000000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_shares')) {
			$table = $schema->createTable('collectives_shares');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('collective_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('page_id', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('token', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('owner', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'collectives_shares_owner');
			$table->addIndex(['token'], 'collectives_shares_token');
			return $schema;
		}

		return null;
	}
}
