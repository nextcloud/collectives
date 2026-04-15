<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Isolated\Symfony\Component\Finder\Finder;

return [
	'prefix' => 'OCA\\Collectives\\Vendor',

	'finders' => [
		Finder::create()
			->files()
			->exclude(['bin', 'bamarni', 'nextcloud', 'psr', 'symfony'])
			->in('.'),
	],

	'patchers' => [
		static function (string $filePath, string $prefix, string $content): string {
			if (str_contains($filePath, '/joomla/string/src/StringHelper.php')) {
				return preg_replace(
					'/(?<!\\\\)(?<![a-zA-Z0-9_])(utf8_[a-zA-Z_]+)\(/',
					'\\\\OCA\\\\Collectives\\\\Vendor\\\\${1}(',
					$content
				);
			}
			return $content;
		},
	],

	'expose-global-functions' => false,
];
