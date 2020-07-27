<?php

namespace OCA\Wiki\Migration;

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

		if (!$schema->hasTable('wiki')) {
			$table = $schema->createTable('wiki');
			$table->addColumn('id', Type::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('circle_unique_id', Type::STRING, [
				'notnull' => true,
				'length' => 15,
			]);
			$table->addColumn('folder_id', Type::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('owner_id', Type::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['circle_unique_id'], 'wiki_circle_unique_id_index');
		}
		return $schema;
	}
}
