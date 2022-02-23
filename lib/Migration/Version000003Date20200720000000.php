<?php

declare(strict_types=1);

namespace OCA\Collectives\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCA\Collectives\Db\Collective;
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

		if (!$schema->hasTable('collectives')) {
			$table = $schema->createTable('collectives');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('circle_unique_id', Types::STRING, [
				'notnull' => true,
				'length' => 31,
			]);
			$table->addColumn('permissions', Types::INTEGER, [
				'notnull' => true,
				// Grant full access to all member levels per default
				'default' => Collective::defaultPermissions,
			]);
			$table->addColumn('emoji', Types::STRING, [
				'notnull' => false,
				'length' => 8,
			]);
			$table->addColumn('page_order', Types::INTEGER, [
				'notnull' => true,
				'default' => Collective::defaultPageOrder,
			]);
			$table->addColumn('trash_timestamp', Types::INTEGER, [
				'notnull' => false,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['circle_unique_id'], 'collectives_circle_id_index');
		}
		return $schema;
	}
}
