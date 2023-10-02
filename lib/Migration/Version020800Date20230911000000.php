<?php

declare(strict_types=1);

namespace OCA\Collectives\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020800Date20230911000000 extends SimpleMigrationStep {
	private IDBConnection $connection;
	private bool $runPageModeMigration = false;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array   $options
	 *
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('collectives_u_settings');
		if (!$table->hasColumn('settings')) {
			$table->addColumn('settings', Types::JSON, [
				'notnull' => true,
				'default' => '[]'
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
				$newSettings = ['page_order' => (int) $row['page_order']];
				$update
					->setParameter('id', (int) $row['id'], IQueryBuilder::PARAM_INT)
					->setParameter('newSettings', $newSettings, IQueryBuilder::PARAM_JSON)
					->executeStatement();
			}
			$result->closeCursor();
		}
	}
}
