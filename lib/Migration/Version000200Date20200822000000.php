<?php

declare(strict_types=1);

namespace OCA\Collectives\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version000200Date20200822000000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_pages')) {
			$table = $schema->createTable('collectives_pages');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('last_user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['file_id'], 'collectives_pages_file_index');
		}
		return $schema;
	}
}
