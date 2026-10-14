<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives;

use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;
use OCP\IAppConfig;

/**
 * Config Lexicon for collectives.
 *
 * Please Add & Manage your Config Keys in that file and keep the Lexicon up to date!
 *
 * {@see ILexicon}
 */
class ConfigLexicon implements ILexicon {
	public const DEFAULT_USER_FOLDER = 'default_user_folder';
	public const ORPHANED_COLLECTIVES = 'orphaned_collectives';
	public const MIGRATED_BACKLINKS = 'migrated_backlinks';
	public const MIGRATED_SLUGS = 'migrated_slugs';
	public const MIGRATED_USER_FOLDER_SETTINGS = 'migrated_user_folder_settings';

	public const USER_FOLDER = 'user_folder';

	#[\Override]
	public function getStrictness(): Strictness {
		return Strictness::IGNORE;
	}

	#[\Override]
	public function getAppConfigs(): array {
		return [
			new Entry(
				self::DEFAULT_USER_FOLDER,
				ValueType::STRING,
				defaultRaw: '',
				definition: 'Default path of the collectives folder in user home directories',
				lazy: false,
				note: 'Empty means a localized "/Collectives" folder. Users can override it with the user config key user_folder.',
			),
			new Entry(
				self::ORPHANED_COLLECTIVES,
				ValueType::ARRAY,
				defaultRaw: [],
				definition: 'IDs of collectives whose team was missing during the last garbage collection run',
				lazy: true,
				flags: IAppConfig::FLAG_INTERNAL,
			),
			new Entry(
				self::MIGRATED_BACKLINKS,
				ValueType::BOOL,
				defaultRaw: false,
				definition: 'Whether the backlinks repair step has completed',
				lazy: false,
				flags: IAppConfig::FLAG_INTERNAL,
			),
			new Entry(
				self::MIGRATED_SLUGS,
				ValueType::BOOL,
				defaultRaw: false,
				definition: 'Whether the slug generation repair step has completed',
				lazy: false,
				flags: IAppConfig::FLAG_INTERNAL,
			),
			new Entry(
				self::MIGRATED_USER_FOLDER_SETTINGS,
				ValueType::BOOL,
				defaultRaw: false,
				definition: 'Whether the user folder settings repair step has completed',
				lazy: false,
				flags: IAppConfig::FLAG_INTERNAL,
			),
		];
	}

	#[\Override]
	public function getUserConfigs(): array {
		return [
			new Entry(
				self::USER_FOLDER,
				ValueType::STRING,
				definition: 'Path of the collectives folder in the user home directory',
				lazy: false,
				note: 'No lexicon default on purpose: callers fall back to the app config key default_user_folder.',
			),
		];
	}
}
