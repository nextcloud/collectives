<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCA\Collectives\Service\CircleHelper;
use OCA\Collectives\Service\SlugService;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version021500Date20240820000000 extends SimpleMigrationStep {
	private bool $runSlugGeneration = false;

	public function __construct(
		private IDBConnection $connection,
		private CircleHelper $circleHelper,
		private SlugService $slugService,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('collectives');
		if (!$table->hasColumn('slug')) {
			$this->runSlugGeneration = true;
			$table->addColumn('slug', Types::STRING, [
				'notnull' => false,
				'default' => false,
				'length' => 255,
			]);

			return $schema;
		}

		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->runSlugGeneration) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->select(['id', 'circle_unique_id'])->from('collectives');
		$result = $query->executeQuery();

		$update = $this->connection->getQueryBuilder();
		$update->update('collectives')
			->set('slug', $update->createParameter('slug'))
			->where($update->expr()->eq('id', $update->createParameter('id')));

		while ($row = $result->fetch()) {
			$circle = $this->circleHelper->getCircle($row['circle_unique_id'], null, true);
			$slug = $this->slugService->generateCollectiveSlug($row['id'], $circle->getSanitizedName());

			$update
				->setParameter('id', (int)$row['id'], IQueryBuilder::PARAM_INT)
				->setParameter('slug', $slug, IQueryBuilder::PARAM_STR)
				->executeStatement();
		}
		$result->closeCursor();
	}
}
