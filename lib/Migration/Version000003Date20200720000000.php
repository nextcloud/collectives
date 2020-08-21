<?php

namespace OCA\Unite\Migration;

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000003Date20200720000000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('unite')) {
			$table = $schema->createTable('unite');
			$table->addColumn('id', Type::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('name', Type::STRING, [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('circle_unique_id', Type::STRING, [
				'notnull' => true,
				'length' => 15,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['circle_unique_id'], 'unite_circle_unique_id_index');
		}
		return $schema;
	}
}
