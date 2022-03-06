<?php

declare(strict_types=1);

namespace OCA\Collectives\Migration;

use Closure;
use Doctrine\DBAL\Types\Types;
use OCP\Constants;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version002000Date20220103000000 extends SimpleMigrationStep {
	/** @var int */
	private const defaultPermissions =
		Constants::PERMISSION_ALL * 100 + // Moderator
		Constants::PERMISSION_ALL;        // Member

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

		$table = $schema->getTable('collectives');
		if (!$table->hasColumn('permissions')) {
			$table->addColumn('permissions', Types::INTEGER, [
				'notnull' => true,
				// Grant full access to all member levels per default
				'default' => self::defaultPermissions,
			]);
		}
		return $schema;
	}
}
