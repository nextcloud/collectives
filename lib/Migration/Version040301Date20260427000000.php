<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040301Date20260427000000 extends SimpleMigrationStep {

	public function __construct(
		private readonly IDBConnection $connection,
	) {
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('collectives_s_docs')) {
			$this->connection->truncateTable('collectives_s_docs', false);
		}
		if ($schema->hasTable('collectives_s_words')) {
			$this->connection->truncateTable('collectives_s_words', false);
		}
		if ($schema->hasTable('collectives_s_files')) {
			$this->connection->truncateTable('collectives_s_files', false);
		}
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->migrateWordsTable($schema);
		$this->migrateDocsTable($schema);
		$this->migrateFilesTable($schema);

		return $schema;
	}

	private function migrateWordsTable(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('collectives_s_words')) {
			return;
		}

		$table = $schema->getTable('collectives_s_words');

		if (!$table->hasColumn('collective_id')) {
			$table->addColumn('collective_id', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		}

		if ($table->hasColumn('circle_unique_id')) {
			$table->dropColumn('circle_unique_id');
		}

		if ($table->hasIndex('s_words_circle_term')) {
			$table->dropIndex('s_words_circle_term');
			$table->addUniqueIndex(['collective_id', 'term'], 's_words_collective_term');
		}

		if ($table->hasIndex('s_words_circle')) {
			$table->dropIndex('s_words_circle');
			$table->addIndex(['collective_id'], 's_words_collective');
		}

		if (!$table->hasColumn('stem')) {
			$table->addColumn('stem', Types::STRING, [
				'notnull' => false,
				'length' => 50,
				'default' => null,
			]);
			$table->addIndex(['collective_id', 'stem'], 's_words_collective_stem');
		}
	}

	private function migrateDocsTable(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('collectives_s_docs')) {
			return;
		}

		$table = $schema->getTable('collectives_s_docs');

		if (!$table->hasColumn('collective_id')) {
			$table->addColumn('collective_id', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		}

		if ($table->hasColumn('circle_unique_id')) {
			$table->dropColumn('circle_unique_id');
		}

		if ($table->hasIndex('s_uniq_docs')) {
			$table->dropIndex('s_uniq_docs');
			$table->addUniqueIndex(['collective_id', 'word_id', 'file_id'], 's_uniq_docs');
		}

		if ($table->hasIndex('s_docs_circle_word')) {
			$table->dropIndex('s_docs_circle_word');
			$table->addIndex(['collective_id', 'word_id'], 's_docs_collective_word');
		}

		if ($table->hasIndex('s_docs_circle_file')) {
			$table->dropIndex('s_docs_circle_file');
			$table->addIndex(['collective_id', 'file_id'], 's_docs_collective_file');
		}
	}

	private function migrateFilesTable(ISchemaWrapper $schema): void {
		if (!$schema->hasTable('collectives_s_files')) {
			return;
		}

		$table = $schema->getTable('collectives_s_files');

		if (!$table->hasColumn('collective_id')) {
			$table->addColumn('collective_id', Types::BIGINT, ['notnull' => true, 'default' => 0]);
		}

		if ($table->hasColumn('circle_unique_id')) {
			$table->dropColumn('circle_unique_id');
		}
	}
}
