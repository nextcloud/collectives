<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030301Date20251029000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('collectives_fts_words')) {
			$table = $schema->createTable('collectives_fts_words');

			$table->addColumn('id', Types::STRING, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('circle_unique_id', Types::STRING, [
				'notnull' => true,
				'length' => 31,
			]);
			$table->addColumn('term', Types::STRING, [
				'notnull' => true,
				'length' => 50,
			]);
			$table->addColumn('num_hits', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('num_files', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['circle_unique_id', 'term'], 'fts_words_circle_term');
			$table->addIndex(['circle_unique_id'], 'fts_words_circle');
		}

		if (!$schema->hasTable('collectives_fts_docs')) {
			$table = $schema->createTable('collectives_fts_docs');

			$table->addColumn('id', Types::STRING, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('circle_unique_id', Types::STRING, [
				'notnull' => true,
				'length' => 31,
			]);
			$table->addColumn('word_id', Types::STRING, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('hit_count', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['circle_unique_id', 'word_id', 'file_id'], 'fts_uniq_docs');
			$table->addIndex(['circle_unique_id', 'word_id'], 'fts_docs_circle_word');
			$table->addIndex(['circle_unique_id', 'file_id'], 'fts_docs_circle_file');
			$table->addIndex(['word_id', 'hit_count'], 'fts_docs_word_hits');
		}

		if (!$schema->hasTable('collectives_fts_files')) {
			$table = $schema->createTable('collectives_fts_files');

			$table->addColumn('id', Types::STRING, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('circle_unique_id', Types::STRING, [
				'notnull' => true,
				'length' => 31,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('path', Types::STRING, [
				'notnull' => true,
				'length' => 1024,
			]);
			$table->addColumn('mtime', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['file_id'], 'fts_files_file_id');
		}

		return $schema;
	}
}
